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
            ->assertJsonPath('orders.0.order_public_id', $visibleOrder->public_id);
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
            ->pluck('order_public_id')
            ->sort()
            ->values()
            ->all();

        $this->actingAs($courier, 'web');
        $livewireOrderIds = Livewire::test(AvailableOrders::class)
            ->viewData('orders')
            ->map(fn ($offer) => optional($offer->order)->public_id)
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
            'dispatch_available_at' => now()->subMinute(),
            'accepted_at' => now()->subMinute(),
            'lat' => 50.4501,
            'lng' => 30.5234,
        ]);

        Sanctum::actingAs($courier);

        $this->getJson('/api/orders/available')
            ->assertOk()
            ->assertJsonPath('orders', []);
    }

    public function test_api_available_orders_are_not_gated_by_legacy_users_online_mirror(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createOnlineCourier();
        $order = $this->createSearchingOrder($client, 'Mirror drift visible');
        OrderOffer::createPrimaryPending($order->id, $courier->id, 120);

        // Drift scenario: legacy mirror says offline, canonical courier status stays online.
        $courier->forceFill([
            'is_online' => false,
            'is_busy' => false,
            'session_state' => User::SESSION_OFFLINE,
        ])->save();

        Sanctum::actingAs($courier);

        $this->getJson('/api/orders/available')
            ->assertOk()
            ->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.order_public_id', $order->public_id);
    }


    public function test_api_returns_allowlisted_offer_contract_only(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createOnlineCourier();
        $order = $this->createSearchingOrder($client, 'Allowlist');
        OrderOffer::createPrimaryPending($order->id, $courier->id, 120);

        Sanctum::actingAs($courier);

        $payload = $this->getJson('/api/orders/available')->assertOk()->json('orders.0');

        $this->assertSame([
            'offer_id',
            'order_public_id',
            'pickup',
            'delivery',
            'price',
            'offer_status',
            'offer_expires_at',
            'seconds_remaining',
            'service',
        ], array_keys($payload));
        $this->assertIsInt($payload['seconds_remaining']);
        $this->assertGreaterThanOrEqual(0, $payload['seconds_remaining']);
        $this->assertArrayNotHasKey('order_id', $payload);
        $this->assertArrayNotHasKey('client_id', $payload);
        $this->assertArrayNotHasKey('lat', $payload);
        $this->assertArrayNotHasKey('lng', $payload);
    }

    public function test_unverified_courier_does_not_receive_available_offers(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createOnlineCourier();
        $order = $this->createSearchingOrder($client, 'Unverified hidden');
        OrderOffer::createPrimaryPending($order->id, $courier->id, 120);

        $courier->forceFill(['is_verified' => false])->save();
        Courier::query()->where('user_id', $courier->id)->update(['is_verified' => false]);

        Sanctum::actingAs($courier);

        $this->getJson('/api/orders/available')
            ->assertOk()
            ->assertJsonPath('orders', []);
    }

    public function test_api_applies_limit_with_default_and_max_bounds(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createOnlineCourier();

        foreach (range(1, 55) as $index) {
            $order = $this->createSearchingOrder($client, 'Order '.$index);
            OrderOffer::createPrimaryPending($order->id, $courier->id, 120 + $index);
        }

        Sanctum::actingAs($courier);

        $this->getJson('/api/orders/available')
            ->assertOk()
            ->assertJsonCount(20, 'orders')
            ->assertJsonPath('pagination.limit', 20);

        $this->getJson('/api/orders/available?limit=500')
            ->assertOk()
            ->assertJsonCount(50, 'orders')
            ->assertJsonPath('pagination.limit', 50)
            ->assertJsonPath('pagination.max_limit', 50);
    }


    public function test_api_invalid_or_empty_limit_falls_back_to_default_limit(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createOnlineCourier();

        foreach (range(1, 25) as $index) {
            $order = $this->createSearchingOrder($client, 'Fallback limit '.$index);
            OrderOffer::createPrimaryPending($order->id, $courier->id, 180 + $index);
        }

        Sanctum::actingAs($courier);

        $this->getJson('/api/orders/available?limit=abc')
            ->assertOk()
            ->assertJsonCount(20, 'orders')
            ->assertJsonPath('pagination.limit', 20);

        $this->getJson('/api/orders/available?limit=')
            ->assertOk()
            ->assertJsonCount(20, 'orders')
            ->assertJsonPath('pagination.limit', 20);
    }

    private function createSearchingOrder(User $client, string $addressText): Order
    {
        return Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => $addressText,
            'price' => 100,
            'dispatch_available_at' => now()->subMinute(),
            'lat' => 50.4501,
            'lng' => 30.5234,
        ]);
    }

    private function createOnlineCourier(): User
    {
        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_verified' => true,
            'is_online' => true,
            'is_busy' => false,
            'session_state' => User::SESSION_READY,
            'last_lat' => 50.4501,
            'last_lng' => 30.5234,
        ]);

        Courier::query()->create([
            'user_id' => $courier->id,
            'status' => Courier::STATUS_ONLINE,
            'is_verified' => true,
            'last_location_at' => now(),
        ]);

        return $courier;
    }
}
