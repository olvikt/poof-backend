<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Livewire\Courier\AvailableOrders;
use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use App\Services\Dispatch\OfferDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CourierVerificationDispatchGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_courier_online_with_fresh_location_does_not_receive_offer(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createCourier(false, false);
        $order = $this->createSearchingOrder($client);

        app(OfferDispatcher::class)->dispatchForOrder($order);
        app(OfferDispatcher::class)->dispatchSearchingOrders(10);

        $this->assertDatabaseCount('order_offers', 0);

        $this->actingAs($courier, 'web');
        Livewire::test(AvailableOrders::class)
            ->assertSee('Перед тим, як отримувати замовлення, потрібно пройти верифікацію.')
            ->assertSee('Перейти до профілю')
            ->assertDontSee('Зараз доступних замовлень немає');
    }

    public function test_pending_review_courier_is_blocked_from_offers(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $this->createCourier(false, false);
        $order = $this->createSearchingOrder($client);

        app(OfferDispatcher::class)->dispatchForOrder($order);

        $this->assertDatabaseCount('order_offers', 0);
    }

    public function test_verified_courier_can_receive_offer(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createCourier(true, true);
        $order = $this->createSearchingOrder($client);

        app(OfferDispatcher::class)->dispatchForOrder($order);

        $this->assertDatabaseHas('order_offers', [
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'status' => OrderOffer::STATUS_PENDING,
        ]);
    }

    private function createSearchingOrder(User $client): Order
    {
        return Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'Gate order',
            'price' => 100,
            'lat' => 50.4501,
            'lng' => 30.5234,
        ]);
    }

    private function createCourier(bool $userVerified, bool $courierVerified): User
    {
        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_online' => true,
            'is_busy' => false,
            'session_state' => User::SESSION_READY,
            'last_lat' => 50.4501,
            'last_lng' => 30.5234,
            'is_verified' => $userVerified,
        ]);

        Courier::query()->create([
            'user_id' => $courier->id,
            'status' => Courier::STATUS_ONLINE,
            'last_location_at' => now(),
            'is_verified' => $courierVerified,
        ]);

        return $courier;
    }
}
