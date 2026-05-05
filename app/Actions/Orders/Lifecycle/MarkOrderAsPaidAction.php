<?php

declare(strict_types=1);

namespace App\Actions\Orders\Lifecycle;

use App\Actions\Subscriptions\CreateSubscriptionExecutionOrderAction;
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
    public function __construct(
        private readonly OrderPromiseResolver $promiseResolver,
        private readonly CreateSubscriptionExecutionOrderAction $createSubscriptionExecutionOrder,
    )
    {
    }

    /**
     * Payment transition to canonical dispatchable state.
     */
    public function handle(Order $order): void
    {
        if (in_array($order->status, [Order::STATUS_DONE, Order::STATUS_CANCELLED, Order::STATUS_EXPIRED], true)) {
            Log::info('order_paid_skipped_terminal', [
                'order_id' => (int) $order->id,
                'subscription_id' => $order->subscription_id !== null ? (int) $order->subscription_id : null,
                'status' => (string) $order->status,
                'reason' => 'terminal_status',
            ]);
            if ($order->payment_status !== Order::PAY_PAID) {
                $order->forceFill([
                    'payment_status' => Order::PAY_PAID,
                ])->save();
            }

            return;
        }

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

        if ($order->order_type === Order::TYPE_SUBSCRIPTION && $order->origin === Order::ORIGIN_CHECKOUT) {
            $this->handleSubscriptionCheckoutPaymentOrder($order);

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

        Log::info('order_marked_paid', [
            'order_id' => (int) $freshOrder->id,
            'subscription_id' => $freshOrder->subscription_id !== null ? (int) $freshOrder->subscription_id : null,
            'status' => (string) $freshOrder->status,
            'reason' => null,
            'payment_status' => (string) $freshOrder->payment_status,
            'counter' => 'order_marked_paid_total',
            'counter_increment' => 1,
        ]);
    }

    private function handleSubscriptionCheckoutPaymentOrder(Order $order): void
    {
        $order->forceFill([
            'payment_status' => Order::PAY_PAID,
            'status' => Order::STATUS_DONE,
        ])->save();

        $freshOrder = $order->fresh();

        if (! $freshOrder) {
            return;
        }

        try {
            $this->syncSubscriptionLifecycleAfterPayment($freshOrder);
        } catch (ValidationException $exception) {
            $freshOrder->forceFill([
                'payment_status' => Order::PAY_PAID,
                'status' => Order::STATUS_CANCELLED,
            ])->save();

            Log::warning('Subscription checkout payment cancelled due to active-scope conflict.', [
                'order_id' => (int) $freshOrder->id,
                'subscription_id' => (int) ($freshOrder->subscription_id ?? 0),
                'reason' => collect($exception->errors())->flatten()->first(),
            ]);

            return;
        }

        if ($freshOrder->subscription_id === null) {
            return;
        }

        $subscription = ClientSubscription::query()->with(['plan', 'address'])->find($freshOrder->subscription_id);

        if (! $subscription) {
            return;
        }

        $runAt = $this->resolveFirstExecutionRunAt($freshOrder);
        $createdExecution = $this->createSubscriptionExecutionOrder->handle($subscription, $runAt);

        if ($createdExecution && $subscription->next_run_at !== null) {
            $currentNextRunAt = CarbonImmutable::instance($subscription->next_run_at);
            if ($currentNextRunAt->equalTo($runAt)) {
                $subscription->forceFill([
                    'next_run_at' => $this->resolveNextRunAt(
                        $runAt,
                        (string) ($subscription->plan?->frequency_type ?? $subscription->meta['frequency_type'] ?? ''),
                    ),
                ])->save();
            }
        }
    }

    private function resolveFirstExecutionRunAt(Order $checkoutOrder): CarbonImmutable
    {
        if ($checkoutOrder->scheduled_date !== null && $checkoutOrder->scheduled_time_from !== null) {
            return CarbonImmutable::parse(sprintf('%s %s', (string) $checkoutOrder->scheduled_date, (string) $checkoutOrder->scheduled_time_from));
        }

        if ($checkoutOrder->scheduled_date !== null) {
            return CarbonImmutable::parse((string) $checkoutOrder->scheduled_date)->setTimeFromTimeString(now()->format('H:i:s'));
        }

        return CarbonImmutable::instance($checkoutOrder->created_at ?? now());
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
