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
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_unpaid_cancelled_and_expired_subscription_orders_are_not_dispatchable_and_not_visible_for_courier(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createOnlineCourier();
        $subscription = $this->createPaidSubscription($client);

        $unpaid = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'origin' => Order::ORIGIN_SUBSCRIPTION,
            'subscription_id' => $subscription->id,
            'address_text' => 'Несплачений',
            'lat' => 50.4501,
            'lng' => 30.5234,
            'price' => 400,
        ]);
        $cancelled = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_CANCELLED,
            'payment_status' => Order::PAY_PAID,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'origin' => Order::ORIGIN_SUBSCRIPTION,
            'subscription_id' => $subscription->id,
            'address_text' => 'Скасований',
            'lat' => 50.4501,
            'lng' => 30.5234,
            'price' => 400,
        ]);
        $expired = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'origin' => Order::ORIGIN_SUBSCRIPTION,
            'subscription_id' => $subscription->id,
            'address_text' => 'Протермінований',
            'lat' => 50.4501,
            'lng' => 30.5234,
            'price' => 400,
            'valid_until_at' => Carbon::now()->subMinute(),
        ]);

        $this->assertFalse($unpaid->fresh()->isDispatchableForOfferPipeline());
        $this->assertFalse($cancelled->fresh()->isDispatchableForOfferPipeline());
        $this->assertFalse($expired->fresh()->isDispatchableForOfferPipeline());

        $this->assertNull(app(\App\Services\Dispatch\OfferDispatcher::class)->dispatchForOrder($unpaid->fresh()));
        $this->assertNull(app(\App\Services\Dispatch\OfferDispatcher::class)->dispatchForOrder($cancelled->fresh()));
        $this->assertNull(app(\App\Services\Dispatch\OfferDispatcher::class)->dispatchForOrder($expired->fresh()));

        $this->assertDatabaseMissing('order_offers', ['order_id' => $unpaid->id, 'courier_id' => $courier->id]);
        $this->assertDatabaseMissing('order_offers', ['order_id' => $cancelled->id, 'courier_id' => $courier->id]);
        $this->assertDatabaseMissing('order_offers', ['order_id' => $expired->id, 'courier_id' => $courier->id]);

        $this->actingAs($courier, 'web');

        Livewire::test(AvailableOrders::class)
            ->assertDontSee('Несплачений')
            ->assertDontSee('Скасований')
            ->assertDontSee('Протермінований');
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

    public function test_paid_subscription_checkout_order_is_not_dispatchable_and_generates_first_execution_order(): void
    {
        Event::fake([\App\Events\OrderCreated::class]);

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createOnlineCourier();
        $plan = SubscriptionPlan::factory()->create(['monthly_price' => 45, 'pickups_per_month' => 10]);
        $address = ClientAddress::createForUser($client->id, [
            'label' => 'home', 'title' => 'Дім', 'address_text' => 'вул. Тест, 1', 'city' => 'Київ', 'street' => 'Тест', 'house' => '1', 'lat' => 50.45, 'lng' => 30.52,
        ]);
        $subscription = ClientSubscription::unguarded(fn (): ClientSubscription => ClientSubscription::query()->create([
            'client_id' => $client->id,
            'subscription_plan_id' => $plan->id,
            'address_id' => $address->id,
            'status' => ClientSubscription::STATUS_ACTIVE,
            'next_run_at' => now()->addDay(),
            'ends_at' => now()->addMonth(),
            'auto_renew' => true,
            'renewals_count' => 0,
        ]));

        $checkout = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'origin' => Order::ORIGIN_CHECKOUT,
            'subscription_id' => $subscription->id,
            'address_id' => $address->id,
            'address_text' => $address->address_text,
            'lat' => 50.45,
            'lng' => 30.52,
            'price' => 45,
            'client_charge_amount' => 45,
            'courier_payout_amount' => 45,
        ]);

        app(MarkOrderAsPaidAction::class)->handle($checkout->fresh());

        $this->assertSame(Order::STATUS_DONE, $checkout->fresh()->status);
        $this->assertFalse($checkout->fresh()->isDispatchableForOfferPipeline());
        Event::assertNotDispatched(\App\Events\OrderCreated::class);

        $execution = Order::query()->where('subscription_id', $subscription->id)->where('origin', Order::ORIGIN_SUBSCRIPTION)->latest('id')->first();
        $this->assertNotNull($execution);
        $this->assertSame(Order::STATUS_SEARCHING, $execution->status);
        $this->assertSame(Order::PAY_PAID, $execution->payment_status);
        $this->assertSame(4, (int) $execution->price);
        $this->assertSame(0, (int) $execution->client_charge_amount);
        $this->assertSame(4, (int) $execution->courier_payout_amount);

        $this->actingAs($courier, 'web');
        Livewire::test(AvailableOrders::class)
            ->assertSee('вул. Тест, 1');
        $this->assertDatabaseMissing('order_offers', ['order_id' => $checkout->id, 'courier_id' => $courier->id]);
    }

    public function test_paid_subscription_order_is_cancelled_without_exception_when_activation_scope_conflicts(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $baseSubscription = $this->createPaidSubscription($client);

        $conflictingSubscription = ClientSubscription::unguarded(fn (): ClientSubscription => ClientSubscription::query()->create([
            'client_id' => $client->id,
            'subscription_plan_id' => $baseSubscription->subscription_plan_id,
            'address_id' => $baseSubscription->address_id,
            'status' => ClientSubscription::STATUS_PAUSED,
            'paused_at' => now(),
            'next_run_at' => now()->addDay(),
            'ends_at' => now()->addMonth(),
            'auto_renew' => true,
        ]));

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'origin' => Order::ORIGIN_SUBSCRIPTION,
            'subscription_id' => $conflictingSubscription->id,
            'address_text' => 'вул. Підписки, 12',
            'lat' => 50.4501,
            'lng' => 30.5234,
            'price' => 400,
            'client_charge_amount' => 400,
        ]);

        app(MarkOrderAsPaidAction::class)->handle($order->fresh());

        $order->refresh();

        $this->assertSame(Order::PAY_PAID, $order->payment_status);
        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertSame(ClientSubscription::STATUS_PAUSED, $conflictingSubscription->fresh()?->status);
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
