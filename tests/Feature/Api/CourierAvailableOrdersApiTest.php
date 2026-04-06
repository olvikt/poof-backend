<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Livewire\Courier\AvailableOrders;
use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class CourierAvailableOrdersApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_returns_only_alive_pending_offers_for_authenticated_courier(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createOnlineCourier();
        $otherCourier = $this->createOnlineCourier();

        $visibleOrder = $this->createSearchingOrder($client, 'Visible');
        $foreignOrder = $this->createSearchingOrder($client, 'Foreign');

        OrderOffer::createPrimaryPending($visibleOrder->id, $courier->id, 120);
        OrderOffer::createPrimaryPending($foreignOrder->id, $otherCourier->id, 120);

        Sanctum::actingAs($courier);

        $this->getJson('/api/orders/available')
            ->assertOk()
            ->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.id', $visibleOrder->id);
    }

    public function test_api_excludes_expired_pending_offer(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createOnlineCourier();
        $order = $this->createSearchingOrder($client, 'Expired pending');

        OrderOffer::query()->create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'type' => OrderOffer::TYPE_PRIMARY,
            'sequence' => 1,
            'status' => OrderOffer::STATUS_PENDING,
            'expires_at' => now()->subSecond(),
        ]);

        Sanctum::actingAs($courier);

        $this->getJson('/api/orders/available')
            ->assertOk()
            ->assertJsonPath('orders', []);
    }

    public function test_api_hides_unrelated_searching_order_without_pending_offer(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createOnlineCourier();

        $this->createSearchingOrder($client, 'Unrelated searching order');

        Sanctum::actingAs($courier);

        $this->getJson('/api/orders/available')
            ->assertOk()
            ->assertJsonPath('orders', []);
    }

    public function test_api_response_parity_with_livewire_available_orders_semantics(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createOnlineCourier();

        $visibleOrder = $this->createSearchingOrder($client, 'Parity visible');
        $hiddenOrder = $this->createSearchingOrder($client, 'Parity hidden');

        OrderOffer::createPrimaryPending($visibleOrder->id, $courier->id, 120);
        OrderOffer::query()->create([
            'order_id' => $hiddenOrder->id,
            'courier_id' => $courier->id,
            'type' => OrderOffer::TYPE_PRIMARY,
            'sequence' => 1,
            'status' => OrderOffer::STATUS_EXPIRED,
            'expires_at' => now()->subSecond(),
        ]);

        Sanctum::actingAs($courier);
        $apiOrderIds = collect($this->getJson('/api/orders/available')->assertOk()->json('orders'))
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $this->actingAs($courier, 'web');
        $livewireOrderIds = Livewire::test(AvailableOrders::class)
            ->viewData('orders')
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $this->assertSame($livewireOrderIds, $apiOrderIds);
    }

    public function test_busy_courier_with_active_order_gets_no_available_orders(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createOnlineCourier();
        $order = $this->createSearchingOrder($client, 'Busy courier hidden');

        OrderOffer::createPrimaryPending($order->id, $courier->id, 120);

        Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_ACCEPTED,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'Active order',
            'price' => 100,
            'accepted_at' => now()->subMinute(),
            'lat' => 50.4501,
            'lng' => 30.5234,
        ]);

        Sanctum::actingAs($courier);

        $this->getJson('/api/orders/available')
            ->assertOk()
            ->assertJsonPath('orders', []);
    }

    private function createSearchingOrder(User $client, string $addressText): Order
    {
        return Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => $addressText,
            'price' => 100,
            'lat' => 50.4501,
            'lng' => 30.5234,
        ]);
    }

    private function createOnlineCourier(): User
    {
        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_online' => true,
            'is_busy' => false,
            'session_state' => User::SESSION_READY,
            'last_lat' => 50.4501,
            'last_lng' => 30.5234,
        ]);

        Courier::query()->create([
            'user_id' => $courier->id,
            'status' => Courier::STATUS_ONLINE,
            'last_location_at' => now(),
        ]);

        return $courier;
    }
}
