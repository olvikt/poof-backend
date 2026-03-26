<?php

namespace Tests\Feature\Courier;

use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourierRuntimeSnapshotApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_courier_runtime_api_returns_canonical_snapshot_contract(): void
    {
        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_online' => false,
            'is_busy' => false,
            'session_state' => User::SESSION_OFFLINE,
        ]);

        Courier::query()->create([
            'user_id' => $courier->id,
            'status' => Courier::STATUS_OFFLINE,
        ]);

        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        Order::factory()->create([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_ACCEPTED,
            'payment_status' => Order::PAY_PAID,
        ]);

        $this->actingAs($courier, 'sanctum');

        $response = $this->getJson('/api/courier/runtime')
            ->assertOk()
            ->assertJsonStructure([
                'runtime' => [
                    'online',
                    'busy',
                    'status',
                    'session_state',
                    'active_order_status',
                    'has_active_order',
                ],
            ]);

        $runtime = $response->json('runtime');

        $this->assertSame($courier->courierRuntimeSnapshot(), $runtime);
        $this->assertTrue($runtime['online']);
        $this->assertTrue($runtime['busy']);
        $this->assertSame(Courier::STATUS_ASSIGNED, $runtime['status']);
        $this->assertSame(User::SESSION_ASSIGNED, $runtime['session_state']);
        $this->assertSame(Order::STATUS_ACCEPTED, $runtime['active_order_status']);
        $this->assertTrue($runtime['has_active_order']);
    }
}
