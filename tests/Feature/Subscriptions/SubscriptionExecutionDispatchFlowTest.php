<?php

declare(strict_types=1);

namespace Tests\Feature\Subscriptions;

use App\Actions\Orders\Completion\ConfirmOrderCompletionByClientAction;
use App\Actions\Orders\Completion\SubmitOrderCompletionByCourierAction;
use App\Actions\Orders\Completion\UploadOrderCompletionProofAction;
use App\Actions\Orders\Lifecycle\MarkOrderAsPaidAction;
use App\Livewire\Client\OrdersList;
use App\Livewire\Courier\AvailableOrders;
use App\Models\ClientAddress;
use App\Models\ClientSubscription;
use App\Models\Courier;
use App\Models\Order;
use App\Models\CourierEarning;
use App\Models\OrderCompletionProof;
use App\Models\OrderCompletionRequest;
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
        $this->assertSame(Order::COMPLETION_POLICY_DOOR_TWO_PHOTO_CLIENT_CONFIRM, $execution->completion_policy);
        $this->assertDatabaseMissing('order_completion_requests', ['order_id' => $execution->id]);
        $this->assertSame(0, OrderCompletionRequest::query()->where('order_id', $execution->id)->count());
        $this->assertDatabaseMissing('courier_earnings', ['order_id' => $execution->id]);

        $this->actingAs($courier, 'web');
        Livewire::test(AvailableOrders::class)
            ->assertSee('вул. Тест, 1');
        $this->assertDatabaseMissing('order_offers', ['order_id' => $checkout->id, 'courier_id' => $courier->id]);
    }


    public function test_subscription_execution_order_remains_searching_after_short_delay(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $plan = SubscriptionPlan::factory()->create(['monthly_price' => 45, 'pickups_per_month' => 10, 'frequency_type' => 'daily']);
        $address = ClientAddress::createForUser($client->id, [
            'label' => 'home', 'title' => 'Дім', 'address_text' => 'вул. Стабільна, 1', 'city' => 'Київ', 'street' => 'Стабільна', 'house' => '1', 'lat' => 50.45, 'lng' => 30.52,
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

        $execution = Order::query()->where('subscription_id', $subscription->id)->where('origin', Order::ORIGIN_SUBSCRIPTION)->latest('id')->firstOrFail();

        sleep(1);
        $execution->refresh();

        $this->assertSame(Order::STATUS_SEARCHING, $execution->status);
        $this->assertSame(Order::PAY_PAID, $execution->payment_status);
        $this->assertDatabaseMissing('order_completion_requests', ['order_id' => $execution->id]);
    }


    public function test_subscription_execution_order_full_completion_lifecycle_with_proofs_and_single_earning(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createOnlineCourier();
        $plan = SubscriptionPlan::factory()->create(['monthly_price' => 45, 'pickups_per_month' => 10]);
        $address = ClientAddress::createForUser($client->id, [
            'label' => 'home', 'title' => 'Дім', 'address_text' => 'вул. ЖЦ, 1', 'city' => 'Київ', 'street' => 'ЖЦ', 'house' => '1', 'lat' => 50.45, 'lng' => 30.52,
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

        $execution = Order::query()->where('subscription_id', $subscription->id)->where('origin', Order::ORIGIN_SUBSCRIPTION)->latest('id')->firstOrFail();
        $execution->forceFill([
            'courier_id' => $courier->id,
            'status' => Order::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ])->save();

        $courier->markBusy();
        $execution->startByCourier($courier);

        $this->assertSame(Order::STATUS_IN_PROGRESS, $execution->fresh()->status);

        $this->assertTrue(app(UploadOrderCompletionProofAction::class)->handle($execution, $courier, OrderCompletionProof::TYPE_DOOR_PHOTO, 'proofs/sub-door.jpg'));
        $this->assertTrue(app(UploadOrderCompletionProofAction::class)->handle($execution, $courier, OrderCompletionProof::TYPE_CONTAINER_PHOTO, 'proofs/sub-container.jpg'));
        $this->assertTrue(app(SubmitOrderCompletionByCourierAction::class)->handle($execution, $courier));

        $request = OrderCompletionRequest::query()->where('order_id', $execution->id)->firstOrFail();
        $this->assertSame(OrderCompletionRequest::STATUS_AWAITING_CLIENT_CONFIRMATION, $request->status);
        $this->assertSame(Order::STATUS_IN_PROGRESS, $execution->fresh()->status);
        $this->assertSame(0, CourierEarning::query()->where('order_id', $execution->id)->count());

        $courier->refresh();
        $this->assertFalse((bool) $courier->is_busy);

        $this->assertTrue(app(ConfirmOrderCompletionByClientAction::class)->handle($execution, $client));
        $this->assertSame(Order::STATUS_DONE, $execution->fresh()->status);
        $this->assertSame(1, CourierEarning::query()->where('order_id', $execution->id)->count());
    }

    public function test_first_execution_uses_checkout_scheduled_slot_and_advances_next_run_without_duplicate_generation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-05 10:00:00'));
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        SubscriptionPlan::factory()->create(['id' => 501, 'monthly_price' => 45, 'pickups_per_month' => 10, 'frequency_type' => 'every_3_days']);
        $plan = SubscriptionPlan::query()->findOrFail(501);
        $address = ClientAddress::createForUser($client->id, [
            'label' => 'home', 'title' => 'Дім', 'address_text' => 'вул. Слот, 1', 'city' => 'Київ', 'street' => 'Слот', 'house' => '1', 'lat' => 50.45, 'lng' => 30.52,
        ]);

        $firstRunAt = Carbon::parse('2026-05-06 14:00:00');
        $subscription = ClientSubscription::unguarded(fn (): ClientSubscription => ClientSubscription::query()->create([
            'client_id' => $client->id,
            'subscription_plan_id' => $plan->id,
            'address_id' => $address->id,
            'status' => ClientSubscription::STATUS_ACTIVE,
            'next_run_at' => $firstRunAt,
            'ends_at' => now()->addMonth(),
            'auto_renew' => true,
            'renewals_count' => 0,
            'meta' => ['frequency_type' => 'every_3_days'],
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
            'scheduled_date' => '2026-05-06',
            'scheduled_time_from' => '14:00',
            'price' => 45,
            'client_charge_amount' => 45,
        ]);

        app(MarkOrderAsPaidAction::class)->handle($checkout->fresh());

        $execution = Order::query()->where('subscription_id', $subscription->id)->where('origin', Order::ORIGIN_SUBSCRIPTION)->firstOrFail();
        $this->assertSame('2026-05-06', $execution->scheduled_date?->format('Y-m-d'));
        $this->assertSame('14:00', substr((string) $execution->scheduled_time_from, 0, 5));
        $this->assertSame(4, (int) $execution->price);

        $subscription->refresh();
        $this->assertSame('2026-05-09 14:00:00', $subscription->next_run_at?->format('Y-m-d H:i:s'));

        $this->artisan('subscriptions:generate-execution-orders', ['--limit' => 10])->assertSuccessful();
        $this->assertSame(1, Order::query()->where('subscription_id', $subscription->id)->where('origin', Order::ORIGIN_SUBSCRIPTION)->count());
        Carbon::setTestNow();
    }

    public function test_repeated_mark_as_paid_does_not_duplicate_first_execution_on_same_slot(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $plan = SubscriptionPlan::factory()->create(['monthly_price' => 45, 'pickups_per_month' => 10, 'frequency_type' => 'every_3_days']);
        $address = ClientAddress::createForUser($client->id, [
            'label' => 'home', 'title' => 'Дім', 'address_text' => 'вул. Дубль, 1', 'city' => 'Київ', 'street' => 'Дубль', 'house' => '1', 'lat' => 50.45, 'lng' => 30.52,
        ]);
        $subscription = ClientSubscription::unguarded(fn (): ClientSubscription => ClientSubscription::query()->create([
            'client_id' => $client->id,
            'subscription_plan_id' => $plan->id,
            'address_id' => $address->id,
            'status' => ClientSubscription::STATUS_ACTIVE,
            'next_run_at' => Carbon::parse('2026-05-06 14:00:00'),
            'ends_at' => now()->addMonth(),
            'auto_renew' => true,
            'renewals_count' => 0,
            'meta' => ['frequency_type' => 'every_3_days'],
        ]));

        $checkout = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'origin' => Order::ORIGIN_CHECKOUT,
            'subscription_id' => $subscription->id,
            'scheduled_date' => '2026-05-06',
            'scheduled_time_from' => '14:00',
            'price' => 45,
        ]);

        app(MarkOrderAsPaidAction::class)->handle($checkout->fresh());
        app(MarkOrderAsPaidAction::class)->handle($checkout->fresh());

        $this->assertSame(1, Order::query()->where('subscription_id', $subscription->id)->where('origin', Order::ORIGIN_SUBSCRIPTION)->count());
    }

    public function test_checkout_subscription_payment_conflict_cancels_checkout_and_does_not_create_execution(): void
    {
        Event::fake([\App\Events\OrderCreated::class]);

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $plan = SubscriptionPlan::factory()->create(['monthly_price' => 45, 'pickups_per_month' => 10, 'frequency_type' => 'every_3_days']);
        $address = ClientAddress::createForUser($client->id, [
            'label' => 'home', 'title' => 'Дім', 'address_text' => 'вул. Конфлікт, 1', 'city' => 'Київ', 'street' => 'Конфлікт', 'house' => '1', 'lat' => 50.45, 'lng' => 30.52,
        ]);

        ClientSubscription::unguarded(fn (): ClientSubscription => ClientSubscription::query()->create([
            'client_id' => $client->id,
            'subscription_plan_id' => $plan->id,
            'address_id' => $address->id,
            'status' => ClientSubscription::STATUS_ACTIVE,
            'next_run_at' => now()->addDay(),
            'ends_at' => now()->addMonth(),
            'auto_renew' => true,
            'renewals_count' => 1,
        ]));

        $checkoutSubscription = ClientSubscription::unguarded(fn (): ClientSubscription => ClientSubscription::query()->create([
            'client_id' => $client->id,
            'subscription_plan_id' => $plan->id,
            'address_id' => $address->id,
            'status' => ClientSubscription::STATUS_PAUSED,
            'paused_at' => now(),
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
            'subscription_id' => $checkoutSubscription->id,
            'scheduled_date' => now()->addDay()->format('Y-m-d'),
            'scheduled_time_from' => '14:00',
            'price' => 45,
            'client_charge_amount' => 45,
        ]);

        app(MarkOrderAsPaidAction::class)->handle($checkout->fresh());

        $checkout->refresh();
        $this->assertSame(Order::PAY_PAID, $checkout->payment_status);
        $this->assertSame(Order::STATUS_CANCELLED, $checkout->status);
        $this->assertSame(0, Order::query()->where('subscription_id', $checkoutSubscription->id)->where('origin', Order::ORIGIN_SUBSCRIPTION)->count());
        Event::assertNotDispatched(\App\Events\OrderCreated::class);
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
