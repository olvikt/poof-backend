<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourierOfferAcceptApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_courier_accepts_available_offer_by_offer_id(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createOnlineCourier();
        $order = $this->createSearchingOrder($client, 'Offer accept');
        $offer = OrderOffer::createPrimaryPending($order->id, $courier->id, 120);

        Sanctum::actingAs($courier);

        $this->postJson('/api/orders/offers/'.$offer->id.'/accept')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(Order::STATUS_ACCEPTED, $order->fresh()->status);
        $this->assertSame(OrderOffer::STATUS_ACCEPTED, $offer->fresh()->status);
    }

    public function test_foreign_courier_cannot_accept_offer(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $owner = $this->createOnlineCourier();
        $intruder = $this->createOnlineCourier();
        $order = $this->createSearchingOrder($client, 'Foreign');
        $offer = OrderOffer::createPrimaryPending($order->id, $owner->id, 120);

        Sanctum::actingAs($intruder);

        $this->postJson('/api/orders/offers/'.$offer->id.'/accept')
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertSame(Order::STATUS_SEARCHING, $order->fresh()->status);
        $this->assertSame(OrderOffer::STATUS_PENDING, $offer->fresh()->status);
    }

    public function test_expired_offer_is_rejected(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createOnlineCourier();
        $order = $this->createSearchingOrder($client, 'Expired');

        $offer = OrderOffer::query()->create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'type' => OrderOffer::TYPE_PRIMARY,
            'sequence' => 1,
            'status' => OrderOffer::STATUS_PENDING,
            'expires_at' => now()->subSecond(),
        ]);

        Sanctum::actingAs($courier);

        $this->postJson('/api/orders/offers/'.$offer->id.'/accept')
            ->assertStatus(409);
    }

    public function test_offer_cannot_be_accepted_twice(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createOnlineCourier();
        $order = $this->createSearchingOrder($client, 'Twice');
        $offer = OrderOffer::createPrimaryPending($order->id, $courier->id, 120);

        Sanctum::actingAs($courier);

        $this->postJson('/api/orders/offers/'.$offer->id.'/accept')->assertOk();
        $this->postJson('/api/orders/offers/'.$offer->id.'/accept')->assertStatus(409);
    }

    public function test_legacy_raw_order_accept_stays_available(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createOnlineCourier();
        $order = $this->createSearchingOrder($client, 'Legacy');

        Sanctum::actingAs($courier);

        $this->postJson('/api/orders/'.$order->id.'/accept')
            ->assertOk()
            ->assertJsonPath('success', true);
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
