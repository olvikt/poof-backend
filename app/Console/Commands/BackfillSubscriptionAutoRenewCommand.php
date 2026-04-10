<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ClientSubscription;
use App\Models\Order;
use Illuminate\Console\Command;

class BackfillSubscriptionAutoRenewCommand extends Command
{
    protected $signature = 'subscriptions:backfill-auto-renew
        {--ids=* : Subscription IDs (repeat option or pass comma-separated values)}
        {--dry-run : Show affected records without writing changes}';

    protected $description = 'Safely backfill auto_renew=true for legacy active paid subscriptions that are currently false';

    public function handle(): int
    {
        $ids = $this->normalizeIds((array) $this->option('ids'));

        if ($ids === []) {
            $this->error('No subscription IDs provided. Use --ids=4 --ids=5 or --ids=4,5.');

            return self::FAILURE;
        }

        $query = ClientSubscription::query()
            ->whereIn('id', $ids)
            ->where('status', ClientSubscription::STATUS_ACTIVE)
            ->where('auto_renew', false)
            ->whereHas('generatedOrders', fn ($orders) => $orders->where('payment_status', Order::PAY_PAID));

        $candidates = $query->get(['id', 'client_id', 'status', 'auto_renew', 'next_run_at', 'ends_at']);

        if ($candidates->isEmpty()) {
            $this->line(json_encode([
                'requested_ids' => $ids,
                'matched' => 0,
                'updated' => 0,
                'dry_run' => (bool) $this->option('dry-run'),
            ], JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $updated = 0;

        if (! (bool) $this->option('dry-run')) {
            $updated = ClientSubscription::query()
                ->whereIn('id', $candidates->pluck('id')->all())
                ->update(['auto_renew' => true]);
        }

        $this->line(json_encode([
            'requested_ids' => $ids,
            'matched' => $candidates->count(),
            'updated' => $updated,
            'dry_run' => (bool) $this->option('dry-run'),
            'matched_ids' => $candidates->pluck('id')->all(),
        ], JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $raw
     * @return array<int, int>
     */
    private function normalizeIds(array $raw): array
    {
        $normalized = collect($raw)
            ->flatMap(fn (string $value) => preg_split('/[\s,]+/', trim($value)) ?: [])
            ->filter(fn (string $value) => $value !== '')
            ->map(fn (string $value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $normalized;
    }
}
