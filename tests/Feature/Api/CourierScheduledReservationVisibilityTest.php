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

    public function test_planned_reservation_not_visible_for_courier_with_active_order(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = User::factory()->create(['role' => User::ROLE_COURIER, 'is_active' => true, 'is_verified' => true, 'is_online' => true, 'is_busy' => false, 'last_lat' => 50.45, 'last_lng' => 30.52]);
        Order::createForTesting(['client_id' => $client->id, 'courier_id' => $courier->id, 'status' => Order::STATUS_ACCEPTED, 'payment_status' => Order::PAY_PAID, 'address_text' => 'active', 'lat' => 50.45, 'lng' => 30.52, 'price' => 10]);
        Order::createForTesting(['client_id' => $client->id, 'status' => Order::STATUS_SEARCHING, 'payment_status' => Order::PAY_PAID, 'service_mode' => Order::SERVICE_MODE_PREFERRED_WINDOW, 'window_from_at' => now()->addHours(5), 'window_to_at' => now()->addHours(7), 'dispatch_available_at' => now()->addHours(4), 'address_text' => 'hidden', 'price' => 50, 'lat' => 50.45, 'lng' => 30.52]);
        Sanctum::actingAs($courier);
        $this->getJson('/api/orders/available')->assertOk()->assertJsonCount(0, 'orders');
    }

    public function test_ineligible_or_far_courier_does_not_see_planned_reservation(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $notVerified = User::factory()->create(['role' => User::ROLE_COURIER, 'is_active' => true, 'is_verified' => false, 'is_online' => true, 'last_lat' => 50.45, 'last_lng' => 30.52]);
        $farCourier = User::factory()->create(['role' => User::ROLE_COURIER, 'is_active' => true, 'is_verified' => true, 'is_online' => true, 'last_lat' => 49.0, 'last_lng' => 35.0]);
        Order::createForTesting(['client_id' => $client->id, 'status' => Order::STATUS_SEARCHING, 'payment_status' => Order::PAY_PAID, 'service_mode' => Order::SERVICE_MODE_PREFERRED_WINDOW, 'window_from_at' => now()->addHours(5), 'window_to_at' => now()->addHours(7), 'dispatch_available_at' => now()->addHours(4), 'address_text' => 'hidden', 'price' => 50, 'lat' => 50.45, 'lng' => 30.52]);
        Sanctum::actingAs($notVerified);
        $this->getJson('/api/orders/available')->assertOk()->assertJsonCount(0, 'orders');
        Sanctum::actingAs($farCourier);
        $this->getJson('/api/orders/available')->assertOk()->assertJsonCount(0, 'orders');
    }

    public function test_legacy_scheduled_window_is_non_null_and_interest_updates_stage(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = User::factory()->create(['role' => User::ROLE_COURIER, 'is_active' => true, 'is_verified' => true, 'is_online' => true, 'last_lat' => 50.45, 'last_lng' => 30.52]);
        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'scheduled_date' => now()->toDateString(),
            'scheduled_time_from' => '12:00:00',
            'scheduled_time_to' => '14:00:00',
            'dispatch_available_at' => now()->addHours(2),
            'address_text' => 'legacy',
            'price' => 150,
            'lat' => 50.45,
            'lng' => 30.52,
        ]);
        Sanctum::actingAs($courier);
        $payload = $this->getJson('/api/orders/available')->assertOk()->json('orders.0');
        $this->assertNotNull($payload['scheduled_window']['from']);
        $this->assertNotNull($payload['scheduled_window']['to']);
        $this->assertNotNull($payload['final_matching_starts_at']);

        $this->postJson('/api/courier/orders/'.$order->id.'/interest')->assertOk();
        $payload = $this->getJson('/api/orders/available')->assertOk()->json('orders.0');
        $this->assertSame('interested', $payload['reservation_stage']);
        $this->assertSame('withdraw_interest', $payload['primary_cta']);
    }
}
