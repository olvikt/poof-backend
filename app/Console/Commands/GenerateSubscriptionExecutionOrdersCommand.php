<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Subscriptions\CreateSubscriptionExecutionOrderAction;
use App\Models\ClientSubscription;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateSubscriptionExecutionOrdersCommand extends Command
{
    public function __construct(private readonly CreateSubscriptionExecutionOrderAction $createSubscriptionExecutionOrder)
    {
        parent::__construct();
    }
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
            'skipped_not_due' => 0,
            'skipped_planned_exhausted' => 0,
            'skipped_period_expired' => 0,
        ];

        foreach ($subscriptions as $subscription) {
            $result = DB::transaction(function () use ($subscription, $now): array {
                $lockedSubscription = ClientSubscription::query()
                    ->with(['plan', 'address'])
                    ->whereKey($subscription->id)
                    ->lockForUpdate()
                    ->first();

                if (! $lockedSubscription instanceof ClientSubscription) {
                    return ['state' => 'missing'];
                }

                if ($lockedSubscription->next_run_at !== null && CarbonImmutable::instance($lockedSubscription->next_run_at)->greaterThan($now)) {
                    return ['state' => 'skipped_not_due', 'subscription' => $lockedSubscription];
                }

                if (! $lockedSubscription->canGenerateNextOrderAutomatically()) {
                    return ['state' => 'skipped_unpaid', 'subscription' => $lockedSubscription];
                }

                $runAt = $this->resolveGenerationSlot(
                    CarbonImmutable::instance($lockedSubscription->next_run_at ?? $now),
                    (string) ($lockedSubscription->plan?->frequency_type ?? $lockedSubscription->meta['frequency_type'] ?? ''),
                    $now,
                );

                if ($lockedSubscription->ends_at !== null && $runAt->greaterThanOrEqualTo(CarbonImmutable::instance($lockedSubscription->ends_at))) {
                    $lockedSubscription->forceFill([
                        'next_run_at' => $this->resolveNextRunAt($runAt, (string) ($lockedSubscription->plan?->frequency_type ?? $lockedSubscription->meta['frequency_type'] ?? '')),
                    ])->save();

                    return ['state' => 'skipped_period_expired', 'subscription' => $lockedSubscription];
                }

                $runAtMinute = $runAt->setSecond(0);
                $slotKey = $runAtMinute->format('Y-m-d H:i:00');

                $existingPendingForSlot = $lockedSubscription->generatedOrders()
                    ->whereIn('payment_status', [Order::PAY_PENDING, Order::PAY_PAID])
                    ->where('origin', Order::ORIGIN_SUBSCRIPTION)
                    ->where(function ($query) use ($slotKey, $runAtMinute): void {
                        $query->where('subscription_run_slot', $slotKey)
                            ->orWhere(function ($legacy) use ($runAtMinute): void {
                                $legacy->whereNull('subscription_run_slot')
                                    ->whereDate('scheduled_date', $runAtMinute->toDateString())
                                    ->whereTime('scheduled_time_from', '>=', $runAtMinute->format('H:i:00'))
                                    ->whereTime('scheduled_time_from', '<=', $runAtMinute->format('H:i:59'));
                            });
                    })
                    ->exists();

                if ($existingPendingForSlot) {
                    return ['state' => 'skipped_duplicate_slot', 'subscription' => $lockedSubscription, 'runAt' => $runAt];
                }

                $existingPending = $lockedSubscription->generatedOrders()
                    ->where('payment_status', Order::PAY_PENDING)
                    ->where('origin', Order::ORIGIN_SUBSCRIPTION)
                    ->exists();

                if ($existingPending) {
                    return ['state' => 'skipped_pending_exists', 'subscription' => $lockedSubscription];
                }

                $periodBounds = $this->resolveBillingPeriodBounds($lockedSubscription, $runAt);
                $plannedExecutionCount = max(1, (int) ($lockedSubscription->plan?->pickups_per_month ?? 1));
                $currentPeriodExecutions = $this->resolveCurrentPeriodExecutionsCount(
                    $lockedSubscription,
                    $periodBounds['start'],
                    $periodBounds['end'],
                );

                if ($currentPeriodExecutions >= $plannedExecutionCount) {
                    $nextPeriodRunAt = $periodBounds['end']->setTime($runAt->hour, $runAt->minute, $runAt->second);
                    $lockedSubscription->forceFill(['next_run_at' => $nextPeriodRunAt])->save();

                    return ['state' => 'skipped_planned_exhausted', 'subscription' => $lockedSubscription];
                }

                $order = $this->createSubscriptionExecutionOrder->handle($lockedSubscription, $runAt);
                if (! $order) {
                    return ['state' => 'skipped_duplicate_slot', 'subscription' => $lockedSubscription, 'runAt' => $runAt];
                }

                $lockedSubscription->forceFill([
                    'next_run_at' => $this->resolveNextRunAt($runAt, (string) ($lockedSubscription->plan?->frequency_type ?? $lockedSubscription->meta['frequency_type'] ?? '')),
                ])->save();

                return ['state' => 'created', 'subscription' => $lockedSubscription, 'order' => $order];
            });

            if (($result['state'] ?? null) === 'skipped_unpaid') {
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
            if (($result['state'] ?? null) === 'skipped_not_due') {
                $summary['skipped_not_due']++;
                logger()->info('subscription_execution_skipped_reason', [
                    'subscription_id' => (int) $subscription->id,
                    'order_id' => null,
                    'status' => (string) $subscription->status,
                    'reason' => 'subscription_run_not_due_after_lock',
                    'counter' => 'subscription_execution_skipped_total',
                    'counter_increment' => 1,
                ]);

                continue;
            }
            if (($result['state'] ?? null) === 'skipped_period_expired') {
                $summary['skipped_period_expired']++;

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
            if (($result['state'] ?? null) === 'skipped_duplicate_slot') {
                $summary['skipped_duplicate_slot']++;
                $runAt = $result['runAt'] ?? $now;

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
            if (($result['state'] ?? null) === 'skipped_pending_exists') {
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
            if (($result['state'] ?? null) === 'skipped_planned_exhausted') {
                $summary['skipped_planned_exhausted']++;

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
            $order = $result['order'] ?? null;
            if (! $order instanceof Order) { continue; }
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
        $nowMinute = $now->setSecond(0);

        while ($slot->setSecond(0)->lessThan($nowMinute)) {
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
