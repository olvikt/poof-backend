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

        $response->assertRedirect(route('client.payments.show', ['order' => $order->public_id]));
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

    public function test_pay_route_blocks_duplicate_active_scope_before_creating_pending_order(): void
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

        ClientSubscription::unguarded(fn (): ClientSubscription => ClientSubscription::query()->create([
            'client_id' => $client->id,
            'subscription_plan_id' => $plan->id,
            'address_id' => $address->id,
            'status' => ClientSubscription::STATUS_ACTIVE,
            'auto_renew' => true,
            'next_run_at' => now()->addDay(),
            'ends_at' => now()->addMonth(),
        ]));

        $target = ClientSubscription::unguarded(fn (): ClientSubscription => ClientSubscription::query()->create([
            'client_id' => $client->id,
            'subscription_plan_id' => $plan->id,
            'address_id' => $address->id,
            'status' => ClientSubscription::STATUS_PAUSED,
            'auto_renew' => true,
            'next_run_at' => now()->addDay(),
            'ends_at' => now()->addMonth(),
        ]));

        $response = $this->actingAs($client, 'web')
            ->post(route('client.subscriptions.pay', $target));

        $response->assertStatus(422);
        $this->assertSame(0, Order::query()->where('subscription_id', $target->id)->count());
    }

    public function test_paid_subscription_checkout_order_is_visible_in_active_subscriptions_tab(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);
        $plan = SubscriptionPlan::factory()->create([
            'name' => 'Пакет Комфорт',
            'monthly_price' => 790,
            'frequency_type' => 'daily',
        ]);
        $address = ClientAddress::createForUser($client->id, [
            'label' => 'home',
            'title' => 'Дім',
            'address_text' => 'вул. Тестова, 15',
            'city' => 'Київ',
            'street' => 'Тестова',
            'house' => '15',
            'lat' => 50.45,
            'lng' => 30.52,
        ]);

        $subscription = ClientSubscription::unguarded(fn (): ClientSubscription => ClientSubscription::query()->create([
            'client_id' => $client->id,
            'subscription_plan_id' => $plan->id,
            'address_id' => $address->id,
            'status' => ClientSubscription::STATUS_ACTIVE,
            'auto_renew' => true,
            'renewals_count' => 0,
            'meta' => ['frequency_type' => 'daily', 'checkout_origin' => 'checkout'],
        ]));

        $checkoutOrder = Order::createForTesting([
            'client_id' => $client->id,
            'subscription_id' => $subscription->id,
            'address_id' => $address->id,
            'address_text' => $address->address_text,
            'origin' => Order::ORIGIN_CHECKOUT,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'price' => 790,
            'client_charge_amount' => 790,
        ]);

        $checkoutOrder->markAsPaid();

        $checkoutOrder = $checkoutOrder->fresh()->load('subscription');
        $subscription = $subscription->fresh()->load('latestPaidCheckoutOrder');

        $this->assertNotNull($subscription, 'Expected subscription to exist after payment.');
        $this->assertSame((int) $subscription->id, (int) $checkoutOrder->subscription_id, 'Expected subscription_id to be linked on checkout order.');
        $this->assertSame(Order::PAY_PAID, $checkoutOrder->payment_status, 'Expected checkout order payment_status=paid.');
        $this->assertSame(Order::STATUS_DONE, $checkoutOrder->status, 'Expected checkout order status=done.');
        $this->assertSame(ClientSubscription::STATUS_ACTIVE, $subscription->status, 'Expected subscription status=active.');
        $this->assertNotNull($subscription->ends_at, 'Expected subscription ends_at to be filled.');
        $this->assertNotNull($subscription->next_run_at, 'Expected subscription next_run_at to be filled.');

        $this->actingAs($client, 'web')
            ->get('/client/subscriptions')
            ->assertOk()
            ->assertSee('Пакет №'.$checkoutOrder->id)
            ->assertSee('Оплачено/Створено: '.$checkoutOrder->created_at?->format('d.m.Y'))
            ->assertSee('Пакет Комфорт')
            ->assertSee('Активна')
            ->assertDontSee('Архів підписок порожній.');
    }
}
