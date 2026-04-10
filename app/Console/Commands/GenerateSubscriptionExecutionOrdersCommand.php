<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ClientSubscription;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class GenerateSubscriptionExecutionOrdersCommand extends Command
{
    protected $signature = 'subscriptions:generate-execution-orders {--limit=100}';

    protected $description = 'Generate due pending payment orders for active paid auto-renew subscriptions';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $subscriptions = ClientSubscription::query()
            ->with(['plan', 'address'])
            ->where('status', ClientSubscription::STATUS_ACTIVE)
            ->where('auto_renew', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->orderBy('next_run_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $summary = [
            'checked' => $subscriptions->count(),
            'created' => 0,
            'skipped_unpaid' => 0,
            'skipped_pending_exists' => 0,
        ];

        foreach ($subscriptions as $subscription) {
            if (! $subscription->canGenerateNextOrderAutomatically()) {
                $summary['skipped_unpaid']++;

                continue;
            }

            $existingPending = $subscription->generatedOrders()
                ->where('payment_status', Order::PAY_PENDING)
                ->where('origin', Order::ORIGIN_SUBSCRIPTION)
                ->exists();

            if ($existingPending) {
                $summary['skipped_pending_exists']++;

                continue;
            }

            $runAt = CarbonImmutable::instance($subscription->next_run_at ?? now());

            Order::createFromLegacyWebContract([
                'client_id' => (int) $subscription->client_id,
                'order_type' => Order::TYPE_SUBSCRIPTION,
                'status' => Order::STATUS_NEW,
                'payment_status' => Order::PAY_PENDING,
                'address_id' => $subscription->address_id,
                'address_text' => (string) ($subscription->address?->address_text ?? 'Адреса підписки'),
                'lat' => $subscription->address?->lat,
                'lng' => $subscription->address?->lng,
                'entrance' => $subscription->address?->entrance,
                'floor' => $subscription->address?->floor,
                'apartment' => $subscription->address?->apartment,
                'intercom' => $subscription->address?->intercom,
                'comment' => null,
                'scheduled_date' => $runAt->toDateString(),
                'scheduled_time_from' => $runAt->format('H:i'),
                'scheduled_time_to' => $runAt->addHours(2)->format('H:i'),
                'handover_type' => Order::HANDOVER_DOOR,
                'bags_count' => (int) ($subscription->plan?->max_bags ?? 1),
                'price' => (int) ($subscription->plan?->monthly_price ?? 0),
                'client_charge_amount' => (int) ($subscription->plan?->monthly_price ?? 0),
                'courier_payout_amount' => (int) ($subscription->plan?->monthly_price ?? 0),
                'system_subsidy_amount' => 0,
                'funding_source' => Order::FUNDING_CLIENT,
                'benefit_type' => null,
                'origin' => Order::ORIGIN_SUBSCRIPTION,
                'subscription_id' => (int) $subscription->id,
                'promo_code' => null,
                'is_trial' => false,
                'trial_days' => 0,
            ]);

            $subscription->forceFill([
                'next_run_at' => $this->resolveNextRunAt($runAt, (string) ($subscription->plan?->frequency_type ?? $subscription->meta['frequency_type'] ?? '')),
            ])->save();

            $summary['created']++;
        }

        $this->line(json_encode($summary, JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function resolveNextRunAt(CarbonImmutable $from, string $frequency): CarbonImmutable
    {
        return match ($frequency) {
            'daily' => $from->addDay(),
            'every_2_days' => $from->addDays(2),
            'every_3_days' => $from->addDays(3),
            default => $from->addDay(),
        };
    }
}
