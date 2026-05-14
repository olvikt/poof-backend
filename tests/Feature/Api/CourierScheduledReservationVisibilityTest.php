<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourierScheduledReservationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_time_scheduled_order_is_visible_pre_window_without_offer(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = User::factory()->create(['role' => User::ROLE_COURIER, 'is_active' => true, 'is_verified' => true, 'is_online' => true]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'service_mode' => Order::SERVICE_MODE_PREFERRED_WINDOW,
            'window_from_at' => now()->addHours(5),
            'window_to_at' => now()->addHours(7),
            'dispatch_available_at' => now()->addHours(4)->addMinutes(30),
            'address_text' => 'Hidden',
            'price' => 150,
            'lat' => 50.45,
            'lng' => 30.52,
        ]);

        Sanctum::actingAs($courier);
        $payload = $this->getJson('/api/orders/available')->assertOk()->json('orders.0');

        $this->assertSame($order->public_id, $payload['order_public_id']);
        $this->assertNull($payload['offer_id']);
        $this->assertSame('express_interest', $payload['primary_cta']);
        $this->assertSame('visible_for_reservation', $payload['reservation_stage']);
    }

    public function test_subscription_execution_scheduled_order_has_same_visibility_semantics(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = User::factory()->create(['role' => User::ROLE_COURIER, 'is_active' => true, 'is_verified' => true, 'is_online' => true]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'service_mode' => Order::SERVICE_MODE_PREFERRED_WINDOW,
            'origin' => Order::ORIGIN_SUBSCRIPTION,
            'subscription_id' => 1,
            'window_from_at' => now()->addHours(5),
            'window_to_at' => now()->addHours(7),
            'dispatch_available_at' => now()->addHours(4)->addMinutes(30),
            'address_text' => 'Hidden',
            'price' => 150,
            'lat' => 50.45,
            'lng' => 30.52,
        ]);

        Sanctum::actingAs($courier);
        $payload = $this->getJson('/api/orders/available')->assertOk()->json('orders.0');

        $this->assertSame($order->public_id, $payload['order_public_id']);
        $this->assertNull($payload['offer_id']);
        $this->assertSame('express_interest', $payload['primary_cta']);
        $this->assertSame('visible_for_reservation', $payload['reservation_stage']);
    }
}
