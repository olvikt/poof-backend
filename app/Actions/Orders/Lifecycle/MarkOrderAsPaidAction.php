<?php

declare(strict_types=1);

namespace App\Actions\Orders\Lifecycle;

use App\Events\OrderCreated;
use App\Models\ClientSubscription;
use App\Models\Order;
use App\Support\Orders\OrderPromiseResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkOrderAsPaidAction
{
    public function __construct(private readonly OrderPromiseResolver $promiseResolver)
    {
    }

    /**
     * Payment transition to canonical dispatchable state.
     */
    public function handle(Order $order): void
    {
        if ($this->hasActivationConflict($order)) {
            $order->forceFill([
                'payment_status' => Order::PAY_PAID,
                'status' => Order::STATUS_CANCELLED,
            ])->save();

            Log::warning('Subscription payment marked as paid but cancelled due to active-scope conflict.', [
                'order_id' => (int) $order->id,
                'subscription_id' => (int) ($order->subscription_id ?? 0),
            ]);

            return;
        }

        $promiseAttributes = $this->promiseResolver->resolveCreateAttributes($order->toArray());

        $order->forceFill([
            'payment_status' => Order::PAY_PAID,
            'status' => Order::STATUS_SEARCHING,
            'service_mode' => $order->service_mode ?? $promiseAttributes['service_mode'],
            'window_from_at' => $order->window_from_at ?? $promiseAttributes['window_from_at'],
            'window_to_at' => $order->window_to_at ?? $promiseAttributes['window_to_at'],
            'valid_until_at' => $order->valid_until_at ?? $promiseAttributes['valid_until_at'],
            'client_wait_preference' => $order->client_wait_preference ?? $promiseAttributes['client_wait_preference'],
            'promise_policy_version' => $order->promise_policy_version ?? $promiseAttributes['promise_policy_version'],
        ])->save();

        $freshOrder = $order->fresh();

        if (! $freshOrder) {
            return;
        }

        try {
            $this->syncSubscriptionLifecycleAfterPayment($freshOrder);
        } catch (ValidationException $exception) {
            $freshOrder->forceFill([
                'status' => Order::STATUS_CANCELLED,
            ])->save();

            Log::warning('Subscription payment cancelled after paid transition due to active-scope conflict.', [
                'order_id' => (int) $freshOrder->id,
                'subscription_id' => (int) ($freshOrder->subscription_id ?? 0),
                'reason' => collect($exception->errors())->flatten()->first(),
            ]);

            return;
        }

        event(new OrderCreated($freshOrder));
    }

    private function syncSubscriptionLifecycleAfterPayment(Order $order): void
    {
        if ($order->subscription_id === null) {
            return;
        }

        DB::transaction(function () use ($order): void {
            $subscription = ClientSubscription::query()
                ->where('id', $order->subscription_id)
                ->lockForUpdate()
                ->first();

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
            ]);

            $subscription->assertNoActiveScopeConflict();
            $subscription->save();
        });
    }

    private function hasActivationConflict(Order $order): bool
    {
        if ($order->subscription_id === null) {
            return false;
        }

        $subscription = ClientSubscription::query()->find($order->subscription_id);

        if (! $subscription) {
            return false;
        }

        return $subscription->overlappingActiveSubscriptionsQuery()->exists();
    }
}
