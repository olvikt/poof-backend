<?php

declare(strict_types=1);

namespace App\Actions\Orders\Lifecycle;

use App\Events\OrderCreated;
use App\Models\ClientSubscription;
use App\Models\Order;
use Carbon\CarbonImmutable;

class MarkOrderAsPaidAction
{
    /**
     * Payment transition to canonical dispatchable state.
     */
    public function handle(Order $order): void
    {
        $order->forceFill([
            'payment_status' => Order::PAY_PAID,
            'status' => Order::STATUS_SEARCHING,
        ])->save();

        $this->syncSubscriptionLifecycleAfterPayment($order->fresh());

        event(new OrderCreated($order));
    }

    private function syncSubscriptionLifecycleAfterPayment(Order $order): void
    {
        if ($order->subscription_id === null) {
            return;
        }

        $subscription = ClientSubscription::query()->find($order->subscription_id);

        if (! $subscription) {
            return;
        }

        $periodStart = CarbonImmutable::instance($order->created_at ?? now());

        $subscription->forceFill([
            'status' => $subscription->status === ClientSubscription::STATUS_CANCELLED
                ? ClientSubscription::STATUS_CANCELLED
                : ClientSubscription::STATUS_ACTIVE,
            'paused_at' => null,
            'last_run_at' => $periodStart,
            'ends_at' => $periodStart->addMonth(),
            'renewals_count' => max(0, (int) $subscription->renewals_count) + 1,
        ])->save();
    }
}
