<?php

declare(strict_types=1);

namespace Tests\Feature\Subscriptions;

use App\Actions\Subscriptions\CreateSubscriptionExecutionOrderAction;
use App\Models\ClientAddress;
use App\Models\ClientSubscription;
use App\Models\Order;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class CreateSubscriptionExecutionOrderActionTest extends TestCase
{
    public function test_it_creates_execution_order_with_dispatch_available_at_without_boundary_exception(): void
    {
        config()->set('dispatch.execution_order_lead_minutes', 30);

        $client = User::factory()->create();
        $address = ClientAddress::factory()->create(['client_id' => $client->id]);
        $plan = SubscriptionPlan::factory()->create();

        $subscription = ClientSubscription::factory()->create([
            'client_id' => $client->id,
            'address_id' => $address->id,
            'subscription_plan_id' => $plan->id,
        ]);

        $runAt = CarbonImmutable::parse('2026-05-09 18:00:00');

        $order = app(CreateSubscriptionExecutionOrderAction::class)->handle($subscription, $runAt);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame('2026-05-09 17:30:00', $order?->dispatch_available_at?->format('Y-m-d H:i:s'));
    }
}
