<?php

declare(strict_types=1);

namespace Tests\Feature\Subscriptions;

use App\Actions\Orders\Lifecycle\MarkOrderAsPaidAction;
use App\Models\ClientSubscription;
use App\Models\Order;
use App\Models\SubscriptionPlan;
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
}
