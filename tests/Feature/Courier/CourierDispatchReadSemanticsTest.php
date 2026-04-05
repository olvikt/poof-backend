<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Livewire\Courier\AvailableOrders;
use App\Livewire\Courier\MyOrders;
use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use App\Services\Dispatch\OfferDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CourierDispatchReadSemanticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_orders_are_built_from_alive_pending_offers_only(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createOnlineCourier();

        $visibleOrder = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'Visible',
            'price' => 100,
            'lat' => 50.4501,
            'lng' => 30.5234,
        ]);

        $hiddenOrder = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'Hidden',
            'price' => 100,
            'lat' => 50.4502,
            'lng' => 30.5235,
        ]);

        OrderOffer::createPrimaryPending($visibleOrder->id, $courier->id, 120);
        OrderOffer::query()->create([
            'order_id' => $hiddenOrder->id,
            'courier_id' => $courier->id,
            'type' => OrderOffer::TYPE_PRIMARY,
            'sequence' => 1,
            'status' => OrderOffer::STATUS_EXPIRED,
            'expires_at' => now()->subSeconds(1),
        ]);

        $this->actingAs($courier, 'web');

        Livewire::test(AvailableOrders::class)
            ->assertViewHas('orders', function ($orders) use ($visibleOrder, $hiddenOrder) {
                return $orders->pluck('id')->values()->all() === [$visibleOrder->id]
                    && ! $orders->pluck('id')->contains($hiddenOrder->id);
            });
    }

    public function test_expired_pending_offer_does_not_appear_in_available_orders(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createOnlineCourier();

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'Expired pending order',
            'price' => 100,
            'lat' => 50.4501,
            'lng' => 30.5234,
        ]);

        OrderOffer::query()->create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'type' => OrderOffer::TYPE_PRIMARY,
            'sequence' => 1,
            'status' => OrderOffer::STATUS_PENDING,
            'expires_at' => now()->subSecond(),
        ]);

        $this->actingAs($courier, 'web');

        Livewire::test(AvailableOrders::class)
            ->assertViewHas('orders', fn ($orders) => $orders->isEmpty());
    }

    public function test_busy_courier_keeps_order_only_in_my_orders_flow_and_gets_no_new_offer(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createOnlineCourier();

        $activeOrder = Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_ACCEPTED,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'Active',
            'price' => 100,
            'accepted_at' => now()->subMinute(),
            'lat' => 50.4501,
            'lng' => 30.5234,
        ]);
        $courier->markBusy();

        $searchingOrder = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'New search',
            'price' => 100,
            'lat' => 50.4510,
            'lng' => 30.5239,
        ]);

        app(OfferDispatcher::class)->dispatchForOrder($searchingOrder);

        $this->assertDatabaseMissing('order_offers', [
            'order_id' => $searchingOrder->id,
            'courier_id' => $courier->id,
            'status' => OrderOffer::STATUS_PENDING,
        ]);

        $this->actingAs($courier, 'web');

        Livewire::test(AvailableOrders::class)
            ->assertViewHas('orders', fn ($orders) => $orders->isEmpty())
            ->assertViewHas('activeOrder', fn ($order) => $order?->id === $activeOrder->id);

        Livewire::test(MyOrders::class)
            ->assertViewHas('orders', fn ($orders) => $orders->pluck('id')->contains($activeOrder->id));
    }

    public function test_dispatch_does_not_create_duplicate_alive_pending_offer_for_same_pair(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createOnlineCourier();

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'Duplicate guard',
            'price' => 100,
            'lat' => 50.4501,
            'lng' => 30.5234,
        ]);

        $dispatcher = app(OfferDispatcher::class);
        $dispatcher->dispatchForOrder($order);
        $dispatcher->dispatchForOrder($order->fresh());

        $this->assertSame(1, OrderOffer::query()
            ->where('order_id', $order->id)
            ->where('courier_id', $courier->id)
            ->where('status', OrderOffer::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->count());
    }

    public function test_dispatch_skips_busy_courier_and_picks_free_one(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $busyCourier = $this->createOnlineCourier(lat: 50.4501, lng: 30.5234);
        $freeCourier = $this->createOnlineCourier(lat: 50.4502, lng: 30.5235);

        Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $busyCourier->id,
            'status' => Order::STATUS_IN_PROGRESS,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'Busy order',
            'price' => 100,
            'accepted_at' => now()->subMinutes(10),
            'started_at' => now()->subMinutes(5),
            'lat' => 50.4501,
            'lng' => 30.5234,
        ]);
        $busyCourier->markDelivering();

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'Pick free courier',
            'price' => 100,
            'lat' => 50.4501,
            'lng' => 30.5234,
        ]);

        $offer = app(OfferDispatcher::class)->dispatchForOrder($order);

        $this->assertNotNull($offer);
        $this->assertSame($freeCourier->id, $offer->courier_id);
        $this->assertNotSame($busyCourier->id, $offer->courier_id);
    }

    public function test_dispatch_keeps_distance_fairness_tie_break_contract(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $olderIdleCourier = $this->createOnlineCourier(
            lat: 50.4501,
            lng: 30.5234,
            lastCompletedAt: now()->subHours(2),
            lastOfferAt: now()->subHours(2),
        );

        $freshCourier = $this->createOnlineCourier(
            lat: 50.4501,
            lng: 30.5234,
            lastCompletedAt: now()->subMinutes(10),
            lastOfferAt: now()->subMinutes(10),
        );

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'Tie break order',
            'price' => 100,
            'lat' => 50.4501,
            'lng' => 30.5234,
        ]);

        $offer = app(OfferDispatcher::class)->dispatchForOrder($order);

        $this->assertNotNull($offer);
        $this->assertSame($olderIdleCourier->id, $offer->courier_id);
        $this->assertNotSame($freshCourier->id, $offer->courier_id);
    }

    private function createOnlineCourier(
        float $lat = 50.4501,
        float $lng = 30.5234,
        ?\Carbon\CarbonInterface $lastCompletedAt = null,
        ?\Carbon\CarbonInterface $lastOfferAt = null,
    ): User {
        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_online' => true,
            'is_busy' => false,
            'session_state' => User::SESSION_READY,
            'last_lat' => $lat,
            'last_lng' => $lng,
            'last_completed_at' => $lastCompletedAt,
            'last_offer_at' => $lastOfferAt,
        ]);

        Courier::query()->create([
            'user_id' => $courier->id,
            'status' => Courier::STATUS_ONLINE,
            'last_location_at' => now(),
        ]);

        return $courier;
    }
}
