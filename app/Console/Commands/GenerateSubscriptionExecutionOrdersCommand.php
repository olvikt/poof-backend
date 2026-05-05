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

    protected $description = 'Generate due paid execution orders for active paid subscriptions';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $now = CarbonImmutable::now();

        $subscriptions = ClientSubscription::query()
            ->with(['plan', 'address'])
            ->where('status', ClientSubscription::STATUS_ACTIVE)
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
            'skipped_duplicate_slot' => 0,
            'skipped_planned_exhausted' => 0,
            'skipped_period_expired' => 0,
        ];

        foreach ($subscriptions as $subscription) {
            if (! $subscription->canGenerateNextOrderAutomatically()) {
                $summary['skipped_unpaid']++;
                logger()->info('subscription_execution_skipped_reason', [
                    'subscription_id' => (int) $subscription->id,
                    'order_id' => null,
                    'status' => (string) $subscription->status,
                    'reason' => 'subscription_not_eligible_for_auto_generation',
                    'counter' => 'subscription_execution_skipped_total',
                    'counter_increment' => 1,
                ]);

                continue;
            }

            $runAt = $this->resolveGenerationSlot(
                CarbonImmutable::instance($subscription->next_run_at ?? $now),
                (string) ($subscription->plan?->frequency_type ?? $subscription->meta['frequency_type'] ?? ''),
                $now,
            );

            if ($subscription->ends_at !== null && $runAt->greaterThanOrEqualTo(CarbonImmutable::instance($subscription->ends_at))) {
                $summary['skipped_period_expired']++;
                $subscription->forceFill([
                    'next_run_at' => $this->resolveNextRunAt($runAt, (string) ($subscription->plan?->frequency_type ?? $subscription->meta['frequency_type'] ?? '')),
                ])->save();

                logger()->info('subscription_execution_skipped_reason', [
                    'subscription_id' => (int) $subscription->id,
                    'order_id' => null,
                    'status' => (string) $subscription->status,
                    'reason' => 'subscription_period_expired',
                    'counter' => 'subscription_execution_skipped_total',
                    'counter_increment' => 1,
                ]);

                continue;
            }

            $runAtMinute = $runAt->setSecond(0);

            $existingPendingForSlot = $subscription->generatedOrders()
                ->whereIn('payment_status', [Order::PAY_PENDING, Order::PAY_PAID])
                ->where('origin', Order::ORIGIN_SUBSCRIPTION)
                ->whereDate('scheduled_date', $runAt->toDateString())
                ->whereTime('scheduled_time_from', '>=', $runAtMinute->format('H:i:00'))
                ->whereTime('scheduled_time_from', '<=', $runAtMinute->format('H:i:59'))
                ->exists();

            if ($existingPendingForSlot) {
                $summary['skipped_duplicate_slot']++;

                logger()->info('subscription_execution_skipped_duplicate', [
                    'subscription_id' => (int) $subscription->id,
                    'order_id' => null,
                    'status' => (string) $subscription->status,
                    'reason' => 'duplicate_pending_for_generation_slot',
                    'order_type' => Order::TYPE_SUBSCRIPTION,
                    'origin' => Order::ORIGIN_SUBSCRIPTION,
                    'scheduled_date' => $runAt->toDateString(),
                    'scheduled_time_from' => $runAt->format('H:i'),
                    'scheduled_time_to' => $runAt->addHours(2)->format('H:i'),
                    'counter' => 'subscription_execution_skipped_duplicate_total',
                    'counter_increment' => 1,
                ]);

                continue;
            }

            $existingPending = $subscription->generatedOrders()
                ->where('payment_status', Order::PAY_PENDING)
                ->where('origin', Order::ORIGIN_SUBSCRIPTION)
                ->exists();

            if ($existingPending) {
                $summary['skipped_pending_exists']++;
                logger()->info('subscription_execution_skipped_reason', [
                    'subscription_id' => (int) $subscription->id,
                    'order_id' => null,
                    'status' => (string) $subscription->status,
                    'reason' => 'pending_execution_order_already_exists',
                    'counter' => 'subscription_execution_skipped_total',
                    'counter_increment' => 1,
                ]);

                continue;
            }

            $periodBounds = $this->resolveBillingPeriodBounds($subscription, $runAt);
            $plannedExecutionCount = max(1, (int) ($subscription->plan?->pickups_per_month ?? 1));
            $currentPeriodExecutions = $this->resolveCurrentPeriodExecutionsCount(
                $subscription,
                $periodBounds['start'],
                $periodBounds['end'],
            );

            if ($currentPeriodExecutions >= $plannedExecutionCount) {
                $summary['skipped_planned_exhausted']++;
                $nextPeriodRunAt = $periodBounds['end']->setTime($runAt->hour, $runAt->minute, $runAt->second);

                $subscription->forceFill([
                    'next_run_at' => $nextPeriodRunAt,
                ])->save();

                logger()->info('subscription_execution_skipped_reason', [
                    'subscription_id' => (int) $subscription->id,
                    'order_id' => null,
                    'status' => (string) $subscription->status,
                    'reason' => 'planned_execution_count_exhausted',
                    'counter' => 'subscription_execution_skipped_total',
                    'counter_increment' => 1,
                ]);

                continue;
            }

            $allocatedAmount = $this->resolveExecutionAllocatedAmount(
                $subscription,
                $currentPeriodExecutions,
                $plannedExecutionCount,
            );

            $order = Order::createFromLegacyWebContract([
                'client_id' => (int) $subscription->client_id,
                'order_type' => Order::TYPE_SUBSCRIPTION,
                'status' => Order::STATUS_SEARCHING,
                'payment_status' => Order::PAY_PAID,
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
                'price' => $allocatedAmount,
                'client_charge_amount' => 0,
                'courier_payout_amount' => $allocatedAmount,
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
            logger()->info('subscription_execution_generated', [
                'subscription_id' => (int) $subscription->id,
                'order_id' => (int) $order->id,
                'status' => (string) $order->status,
                'reason' => null,
                'payment_status' => (string) $order->payment_status,
                'counter' => 'subscription_execution_generated_total',
                'counter_increment' => 1,
            ]);
        }

        $this->line(json_encode($summary, JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function resolveExecutionAllocatedAmount(
        ClientSubscription $subscription,
        int $currentPeriodExecutions,
        int $plannedExecutionCount,
    ): int
    {
        $totalPaidAmount = max(0, (int) ($subscription->plan?->monthly_price ?? 0));
        $baseAmount = intdiv($totalPaidAmount, $plannedExecutionCount);
        $remainder = $totalPaidAmount % $plannedExecutionCount;
        $currentExecutionIndex = max(0, $currentPeriodExecutions);

        if ($currentExecutionIndex === $plannedExecutionCount - 1) {
            return $baseAmount + $remainder;
        }

        return $baseAmount;
    }

    private function resolveCurrentPeriodExecutionsCount(
        ClientSubscription $subscription,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
    ): int {
        return $subscription->generatedOrders()
            ->where('origin', Order::ORIGIN_SUBSCRIPTION)
            ->whereDate('scheduled_date', '>=', $periodStart->toDateString())
            ->whereDate('scheduled_date', '<', $periodEnd->toDateString())
            ->count();
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    private function resolveBillingPeriodBounds(ClientSubscription $subscription, CarbonImmutable $runAt): array
    {
        $periodStart = $runAt->startOfMonth()->startOfDay();
        $periodEnd = $periodStart->addMonth();

        return [
            'start' => $periodStart,
            'end' => $periodEnd,
        ];
    }

    private function resolveGenerationSlot(CarbonImmutable $nextRunAt, string $frequency, CarbonImmutable $now): CarbonImmutable
    {
        $slot = $nextRunAt;

        while ($slot->lessThan($now)) {
            $slot = $this->resolveNextRunAt($slot, $frequency);
        }

        return $slot;
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
