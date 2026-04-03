<?php

declare(strict_types=1);

namespace Tests\Feature\Subscriptions;

use App\Actions\Orders\Lifecycle\MarkOrderAsPaidAction;
use App\Livewire\Client\OrdersList;
use App\Livewire\Courier\AvailableOrders;
use App\Models\ClientAddress;
use App\Models\ClientSubscription;
use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubscriptionExecutionDispatchFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_subscription_execution_order_creates_offer_and_is_visible_for_courier(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createOnlineCourier();
        $subscription = $this->createPaidSubscription($client);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'origin' => Order::ORIGIN_SUBSCRIPTION,
            'subscription_id' => $subscription->id,
            'address_text' => 'вул. Підписки, 12',
            'lat' => 50.4501,
            'lng' => 30.5234,
            'price' => 400,
            'client_charge_amount' => 400,
        ]);

        app(MarkOrderAsPaidAction::class)->handle($order->fresh());

        $order->refresh();

        $this->assertSame(Order::PAY_PAID, $order->payment_status);
        $this->assertSame(Order::STATUS_SEARCHING, $order->status);

        $offer = OrderOffer::query()
            ->where('order_id', $order->id)
            ->where('courier_id', $courier->id)
            ->where('status', OrderOffer::STATUS_PENDING)
            ->first();

        $this->assertNotNull($offer);

        $this->actingAs($courier, 'web');

        Livewire::test(AvailableOrders::class)
            ->assertSee('Пошук замовлень...');
    }

    public function test_orders_list_excludes_subscription_execution_orders(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $subscription = $this->createPaidSubscription($client);

        Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'вул. Разова, 1',
            'order_type' => Order::TYPE_ONE_TIME,
            'origin' => Order::ORIGIN_CHECKOUT,
            'price' => 150,
        ]);

        $subscriptionOrder = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'вул. Підписки, 2',
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'origin' => Order::ORIGIN_SUBSCRIPTION,
            'subscription_id' => $subscription->id,
            'price' => 450,
        ]);

        $this->actingAs($client, 'web');

        Livewire::test(OrdersList::class)
            ->assertSee('вул. Разова, 1')
            ->assertDontSee('вул. Підписки, 2');

        $this->assertTrue($subscriptionOrder->fresh()->isSubscriptionExecution());
    }

    public function test_paid_one_time_order_still_creates_offer_in_same_dispatch_pipeline(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createOnlineCourier();

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'address_text' => 'вул. Разова, 5',
            'order_type' => Order::TYPE_ONE_TIME,
            'origin' => Order::ORIGIN_CHECKOUT,
            'lat' => 50.4501,
            'lng' => 30.5234,
            'price' => 199,
        ]);

        app(MarkOrderAsPaidAction::class)->handle($order->fresh());

        $this->assertDatabaseHas('order_offers', [
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'status' => OrderOffer::STATUS_PENDING,
        ]);
    }

    public function test_paused_subscription_cannot_be_renewed_until_resumed(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $subscription = $this->createPaidSubscription($client, [
            'status' => ClientSubscription::STATUS_PAUSED,
            'paused_at' => now(),
        ]);

        $this->actingAs($client, 'web')
            ->post(route('client.subscriptions.renew', $subscription))
            ->assertStatus(422);

        $subscription->forceFill([
            'status' => ClientSubscription::STATUS_ACTIVE,
            'paused_at' => null,
        ])->save();

        $this->actingAs($client, 'web')
            ->post(route('client.subscriptions.renew', $subscription))
            ->assertRedirect();
    }

    private function createOnlineCourier(): User
    {
        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_online' => true,
            'is_busy' => false,
            'session_state' => User::SESSION_READY,
            'last_lat' => 50.4502,
            'last_lng' => 30.5232,
            'last_offer_at' => now()->subDay(),
            'last_completed_at' => now()->subDay(),
        ]);

        Courier::query()->create([
            'user_id' => $courier->id,
            'status' => Courier::STATUS_ONLINE,
            'last_location_at' => now(),
        ]);

        return $courier;
    }

    private function createPaidSubscription(User $client, array $overrides = []): ClientSubscription
    {
        $plan = SubscriptionPlan::factory()->create([
            'monthly_price' => 450,
            'pickups_per_month' => 4,
        ]);

        $address = ClientAddress::createForUser($client->id, [
            'label' => 'home',
            'title' => 'Дім',
            'address_text' => 'вул. Підписки, 12',
            'city' => 'Київ',
            'street' => 'Підписки',
            'house' => '12',
            'lat' => 50.4501,
            'lng' => 30.5234,
        ]);

        $subscription = ClientSubscription::unguarded(function () use ($client, $plan, $address, $overrides): ClientSubscription {
            return ClientSubscription::query()->create(array_merge([
                'client_id' => $client->id,
                'subscription_plan_id' => $plan->id,
                'address_id' => $address->id,
                'status' => ClientSubscription::STATUS_ACTIVE,
                'ends_at' => now()->addDays(14),
                'next_run_at' => now()->addDay(),
                'auto_renew' => true,
                'renewals_count' => 1,
            ], $overrides));
        });

        Order::createForTesting([
            'client_id' => $client->id,
            'subscription_id' => $subscription->id,
            'payment_status' => Order::PAY_PAID,
            'status' => Order::STATUS_DONE,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'origin' => Order::ORIGIN_SUBSCRIPTION,
            'price' => 450,
            'client_charge_amount' => 450,
            'address_text' => 'вул. Підписки, 12',
        ]);

        return $subscription;
    }
}
