<?php

namespace Tests\Feature\Client;

use App\Models\ClientAddress;
use App\Models\ClientSubscription;
use App\Models\Order;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionCheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpaid_subscription_uses_subscription_payment_entrypoint_and_redirects_to_payment_page(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);
        $plan = SubscriptionPlan::factory()->create(['monthly_price' => 500]);
        $address = ClientAddress::createForUser($client->id, [
            'label' => 'home',
            'title' => 'Дім',
            'address_text' => 'вул. Платіжна, 11',
            'city' => 'Київ',
            'street' => 'Платіжна',
            'house' => '11',
            'lat' => 50.45,
            'lng' => 30.52,
        ]);

        $subscription = ClientSubscription::unguarded(fn (): ClientSubscription => ClientSubscription::query()->create([
            'client_id' => $client->id,
            'subscription_plan_id' => $plan->id,
            'address_id' => $address->id,
            'status' => ClientSubscription::STATUS_ACTIVE,
            'auto_renew' => true,
            'next_run_at' => now()->addDay(),
        ]));

        $this->actingAs($client, 'web')
            ->get(route('client.subscriptions'))
            ->assertSee(route('client.subscriptions.pay', $subscription), false)
            ->assertDontSee(route('client.order.create', ['subscription_id' => $subscription->id]), false);

        $response = $this->actingAs($client, 'web')
            ->post(route('client.subscriptions.pay', $subscription));

        $order = Order::query()->where('subscription_id', $subscription->id)->firstOrFail();

        $response->assertRedirect(route('client.payments.show', $order));
        $this->assertSame(Order::ORIGIN_SUBSCRIPTION, $order->origin);
        $this->assertSame(Order::TYPE_SUBSCRIPTION, $order->order_type);
    }

    public function test_paid_subscription_shows_renew_cta_and_hides_cancel_cta(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);
        $plan = SubscriptionPlan::factory()->create(['monthly_price' => 500]);

        $subscription = ClientSubscription::unguarded(fn (): ClientSubscription => ClientSubscription::query()->create([
            'client_id' => $client->id,
            'subscription_plan_id' => $plan->id,
            'status' => ClientSubscription::STATUS_ACTIVE,
            'auto_renew' => true,
            'ends_at' => now()->addMonth(),
        ]));

        Order::createForTesting([
            'client_id' => $client->id,
            'subscription_id' => $subscription->id,
            'payment_status' => Order::PAY_PAID,
            'status' => Order::STATUS_DONE,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'price' => 500,
            'client_charge_amount' => 500,
            'address_text' => 'вул. Статусна, 1',
        ]);

        $this->actingAs($client, 'web')
            ->get(route('client.subscriptions'))
            ->assertSee('Продовжити')
            ->assertSee('Докладніше')
            ->assertDontSee('Скасувати');
    }
}

