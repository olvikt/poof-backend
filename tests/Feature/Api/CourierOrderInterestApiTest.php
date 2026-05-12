<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourierOrderInterestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_courier_can_express_interest_without_assignment(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = User::factory()->create(['role' => User::ROLE_COURIER, 'is_active' => true, 'is_verified' => true]);
        Courier::factory()->create(['user_id' => $courier->id, 'status' => Courier::STATUS_ONLINE, 'is_verified' => true]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'Hidden',
            'price' => 100,
            'dispatch_available_at' => now()->addHour(),
            'window_from_at' => now()->addHours(2),
            'window_to_at' => now()->addHours(4),
            'lat' => 50.4501,
            'lng' => 30.5234,
        ]);

        Sanctum::actingAs($courier);

        $this->postJson("/api/courier/orders/{$order->id}/interest")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('courier_order_interests', [
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'status' => 'interested',
        ]);
        $this->assertNull($order->fresh()->courier_id);
    }
}
