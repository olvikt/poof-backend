<?php

declare(strict_types=1);

namespace Tests\Feature\Subscriptions;

use App\Actions\Orders\Lifecycle\MarkOrderAsPaidAction;
use App\Models\ClientSubscription;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\SubscriptionPlan;
use App\Services\Dispatch\OfferDispatcher;
use Carbon\Carbon;
use Tests\TestCase;

class SubscriptionDispatchAvailabilityTest extends TestCase
{
    public function test_paid_before_noon_schedules_first_run_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-07 11:00:00'));

        $plan = SubscriptionPlan::factory()->create();
        $subscription = ClientSubscription::factory()->create(['subscription_plan_id' => $plan->id]);
        $checkout = Order::createForTesting([
            'client_id' => $subscription->client_id,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'origin' => Order::ORIGIN_CHECKOUT,
            'subscription_id' => $subscription->id,
            'scheduled_time_from' => '16:00',
            'scheduled_time_to' => '18:00',
        ]);

        app(MarkOrderAsPaidAction::class)->handle($checkout);

        $subscription->refresh();
        $this->assertSame('2026-05-07 16:00:00', $subscription->starts_at?->format('Y-m-d H:i:s'));
    }

    public function test_paid_after_noon_ignores_legacy_scheduled_date_and_uses_tomorrow(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-07 13:00:00'));

        $plan = SubscriptionPlan::factory()->create();
        $subscription = ClientSubscription::factory()->create(['subscription_plan_id' => $plan->id]);
        $checkout = Order::createForTesting([
            'client_id' => $subscription->client_id,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'origin' => Order::ORIGIN_CHECKOUT,
            'subscription_id' => $subscription->id,
            'scheduled_date' => '2026-05-07',
            'scheduled_time_from' => '16:00',
            'scheduled_time_to' => '18:00',
        ]);

        app(MarkOrderAsPaidAction::class)->handle($checkout);

        $subscription->refresh();
        $this->assertSame('2026-05-08 16:00:00', $subscription->starts_at?->format('Y-m-d H:i:s'));
    }

    public function test_cutoff_uses_payment_time_not_order_created_at(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-07 11:00:00'));

        $plan = SubscriptionPlan::factory()->create();
        $subscription = ClientSubscription::factory()->create(['subscription_plan_id' => $plan->id]);
        $checkout = Order::createForTesting([
            'client_id' => $subscription->client_id,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'origin' => Order::ORIGIN_CHECKOUT,
            'subscription_id' => $subscription->id,
            'scheduled_time_from' => '16:00',
            'scheduled_time_to' => '18:00',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-05-07 13:00:00'));
        app(MarkOrderAsPaidAction::class)->handle($checkout->fresh());

        $subscription->refresh();
        $this->assertSame('2026-05-08 16:00:00', $subscription->starts_at?->format('Y-m-d H:i:s'));
    }

    public function test_dispatch_gating_blocks_offers_until_available_at(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-07 10:00:00'));
        $order = Order::createForTesting([
            'client_id' => ClientSubscription::factory()->create()->client_id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'origin' => Order::ORIGIN_SUBSCRIPTION,
            'dispatch_available_at' => Carbon::parse('2026-05-08 10:00:00'),
        ]);

        event(new \App\Events\OrderCreated($order));
        $this->assertSame(0, OrderOffer::query()->where('order_id', $order->id)->count());

        app(OfferDispatcher::class)->dispatchSearchingOrders(20);
        $this->assertSame(0, OrderOffer::query()->where('order_id', $order->id)->count());
        $this->assertTrue($order->fresh()->isDispatchDeferred());

        Carbon::setTestNow(Carbon::parse('2026-05-08 10:00:00'));
        app(OfferDispatcher::class)->dispatchSearchingOrders(20);
        $this->assertFalse($order->fresh()->isDispatchDeferred());
    }
}
