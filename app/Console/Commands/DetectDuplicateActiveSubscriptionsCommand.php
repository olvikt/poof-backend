<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ClientSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DetectDuplicateActiveSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:detect-duplicate-active
        {--remediate : Pause duplicate active subscriptions, keeping the oldest active row in scope}
        {--dry-run : Print remediation plan without writing changes}';

    protected $description = 'Detect and optionally remediate duplicate active subscriptions within client/address scope';

    public function handle(): int
    {
        $groups = DB::table('client_subscriptions')
            ->select([
                'client_id',
                'address_id',
                DB::raw('COUNT(*) as active_count'),
                DB::raw('MIN(id) as keeper_id'),
            ])
            ->where('status', ClientSubscription::STATUS_ACTIVE)
            ->groupBy('client_id', 'address_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('client_id')
            ->orderBy('address_id')
            ->get();

        $duplicateIds = $groups
            ->flatMap(function (object $group): Collection {
                return ClientSubscription::query()
                    ->where('client_id', (int) $group->client_id)
                    ->where('address_id', $group->address_id)
                    ->where('status', ClientSubscription::STATUS_ACTIVE)
                    ->where('id', '!=', (int) $group->keeper_id)
                    ->orderBy('id')
                    ->pluck('id');
            })
            ->map(fn ($id): int => (int) $id)
            ->values();

        $updated = 0;
        $remediate = (bool) $this->option('remediate');
        $dryRun = (bool) $this->option('dry-run');

        if ($remediate && ! $dryRun && $duplicateIds->isNotEmpty()) {
            $updated = ClientSubscription::query()
                ->whereIn('id', $duplicateIds->all())
                ->update([
                    'status' => ClientSubscription::STATUS_PAUSED,
                    'paused_at' => now(),
                    'active_scope_key' => null,
                ]);
        }

        $this->line(json_encode([
            'duplicate_scope_count' => $groups->count(),
            'duplicate_subscription_count' => $duplicateIds->count(),
            'remediate' => $remediate,
            'dry_run' => $dryRun,
            'updated' => $updated,
            'scopes' => $groups->map(fn (object $group): array => [
                'client_id' => (int) $group->client_id,
                'address_id' => $group->address_id !== null ? (int) $group->address_id : null,
                'active_count' => (int) $group->active_count,
                'keeper_id' => (int) $group->keeper_id,
            ])->values()->all(),
            'duplicate_ids' => $duplicateIds->all(),
        ], JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
