<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions;

use App\Models\ClientSubscription;
use App\Models\Order;
use Carbon\CarbonImmutable;

class CreateSubscriptionExecutionOrderAction
{
    public function handle(ClientSubscription $subscription, ?CarbonImmutable $runAt = null): ?Order
    {
        $runAt ??= CarbonImmutable::now();

        $runAtMinute = $runAt->setSecond(0);

        $existing = $subscription->generatedOrders()
            ->whereIn('payment_status', [Order::PAY_PENDING, Order::PAY_PAID])
            ->where('origin', Order::ORIGIN_SUBSCRIPTION)
            ->whereDate('scheduled_date', $runAt->toDateString())
            ->whereTime('scheduled_time_from', '>=', $runAtMinute->format('H:i:00'))
            ->whereTime('scheduled_time_from', '<=', $runAtMinute->format('H:i:59'))
            ->exists();

        if ($existing) {
            return null;
        }

        $plannedExecutionCount = max(1, (int) ($subscription->plan?->pickups_per_month ?? 1));
        $periodStart = $runAt->startOfMonth()->startOfDay();
        $periodEnd = $periodStart->addMonth();
        $currentPeriodExecutions = $subscription->generatedOrders()
            ->where('origin', Order::ORIGIN_SUBSCRIPTION)
            ->whereDate('scheduled_date', '>=', $periodStart->toDateString())
            ->whereDate('scheduled_date', '<', $periodEnd->toDateString())
            ->count();

        $allocatedAmount = $this->resolveExecutionAllocatedAmount($subscription, $currentPeriodExecutions, $plannedExecutionCount);

        return Order::createFromLegacyWebContract([
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
    }

    private function resolveExecutionAllocatedAmount(ClientSubscription $subscription, int $currentPeriodExecutions, int $plannedExecutionCount): int
    {
        $totalPaidAmount = max(0, (int) ($subscription->plan?->monthly_price ?? 0));
        $baseAmount = intdiv($totalPaidAmount, $plannedExecutionCount);
        $remainder = $totalPaidAmount % $plannedExecutionCount;

        if (max(0, $currentPeriodExecutions) === $plannedExecutionCount - 1) {
            return $baseAmount + $remainder;
        }

        return $baseAmount;
    }
}
