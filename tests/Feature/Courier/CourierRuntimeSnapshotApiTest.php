<?php

namespace Tests\Feature\Courier;

use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use App\Support\CourierRuntimeSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $this->assertSame(CourierRuntimeSnapshot::CONTRACT_KEYS, array_keys($runtime));
        $this->assertTrue($runtime['online']);
        $this->assertTrue($runtime['busy']);
        $this->assertSame(Courier::STATUS_ASSIGNED, $runtime['status']);
        $this->assertSame(User::SESSION_ASSIGNED, $runtime['session_state']);
        $this->assertSame(Order::STATUS_ACCEPTED, $runtime['active_order_status']);
        $this->assertTrue($runtime['has_active_order']);
    }

    public function test_snapshot_contract_stays_backend_canonical_after_manual_users_table_drift(): void
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
            'status' => Courier::STATUS_ONLINE,
        ]);

        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        Order::factory()->create([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_IN_PROGRESS,
            'payment_status' => Order::PAY_PAID,
        ]);

        // Emulate partial stale UI/backend drift in legacy flags.
        $courier->update([
            'is_online' => false,
            'is_busy' => false,
            'session_state' => User::SESSION_OFFLINE,
        ]);
        $courier->courierProfile()->update(['status' => Courier::STATUS_OFFLINE]);

        $this->actingAs($courier, 'sanctum');
        $runtime = $this->getJson('/api/courier/runtime')
            ->assertOk()
            ->json('runtime');

        $courier->refresh();

        $this->assertSame(CourierRuntimeSnapshot::CONTRACT_KEYS, array_keys($runtime));
        $this->assertTrue($runtime['online']);
        $this->assertTrue($runtime['busy']);
        $this->assertSame(Courier::STATUS_DELIVERING, $runtime['status']);
        $this->assertSame(User::SESSION_IN_PROGRESS, $runtime['session_state']);
        $this->assertSame(Order::STATUS_IN_PROGRESS, $runtime['active_order_status']);
        $this->assertTrue($runtime['has_active_order']);

        $this->assertTrue((bool) $courier->is_online);
        $this->assertTrue((bool) $courier->is_busy);
    }


    public function test_runtime_snapshot_reads_active_order_state_once_per_call(): void
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

        DB::flushQueryLog();
        DB::enableQueryLog();

        $runtime = $courier->fresh(['courierProfile'])->courierRuntimeSnapshot();

        $this->assertTrue((bool) ($runtime['has_active_order'] ?? false));

        $ordersReads = collect(DB::getQueryLog())
            ->filter(function (array $entry): bool {
                $query = strtolower((string) ($entry['query'] ?? ''));

                return str_contains($query, 'from "orders"')
                    || str_contains($query, 'from `orders`')
                    || str_contains($query, 'from orders');
            })
            ->count();

        $this->assertSame(1, $ordersReads, 'courierRuntimeSnapshot should read active orders once per snapshot call.');
    }

    public function test_snapshot_projects_online_busy_and_session_from_canonical_status_matrix(): void
    {
        $cases = [
            'offline_no_active_order' => [
                'courier_status' => Courier::STATUS_OFFLINE,
                'order_status' => null,
                'online' => false,
                'busy' => false,
                'session_state' => User::SESSION_OFFLINE,
                'active_order_status' => null,
            ],
            'online_no_active_order' => [
                'courier_status' => Courier::STATUS_ONLINE,
                'order_status' => null,
                'online' => true,
                'busy' => false,
                'session_state' => User::SESSION_READY,
                'active_order_status' => null,
            ],
            'accepted_active_order' => [
                'courier_status' => Courier::STATUS_ONLINE,
                'order_status' => Order::STATUS_ACCEPTED,
                'online' => true,
                'busy' => true,
                'session_state' => User::SESSION_ASSIGNED,
                'active_order_status' => Order::STATUS_ACCEPTED,
            ],
            'in_progress_active_order' => [
                'courier_status' => Courier::STATUS_ONLINE,
                'order_status' => Order::STATUS_IN_PROGRESS,
                'online' => true,
                'busy' => true,
                'session_state' => User::SESSION_IN_PROGRESS,
                'active_order_status' => Order::STATUS_IN_PROGRESS,
            ],
        ];

        foreach ($cases as $case) {
            $courier = User::factory()->create([
                'role' => User::ROLE_COURIER,
                'is_active' => true,
                // Deliberately stale mirrors must not affect snapshot truth.
                'is_online' => false,
                'is_busy' => false,
                'session_state' => User::SESSION_OFFLINE,
            ]);

            Courier::query()->create([
                'user_id' => $courier->id,
                'status' => $case['courier_status'],
            ]);

            if ($case['order_status'] !== null) {
                $client = User::factory()->create([
                    'role' => User::ROLE_CLIENT,
                    'is_active' => true,
                ]);

                Order::factory()->create([
                    'client_id' => $client->id,
                    'courier_id' => $courier->id,
                    'status' => $case['order_status'],
                    'payment_status' => Order::PAY_PAID,
                ]);
            }

            $runtime = $courier->fresh(['courierProfile'])->courierRuntimeSnapshot();

            $this->assertSame($case['online'], $runtime['online']);
            $this->assertSame($case['busy'], $runtime['busy']);
            $this->assertSame($case['session_state'], $runtime['session_state']);
            $this->assertSame($case['active_order_status'], $runtime['active_order_status']);
            $this->assertSame($case['active_order_status'] !== null, $runtime['has_active_order']);
        }
    }

    public function test_forced_offline_with_active_order_is_blocked_and_repaired_to_canonical_busy_status(): void
    {
        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_online' => true,
            'is_busy' => false,
            'session_state' => User::SESSION_READY,
        ]);

        Courier::query()->create([
            'user_id' => $courier->id,
            'status' => Courier::STATUS_ONLINE,
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

        $courier->goOffline(true);
        $runtime = $courier->fresh(['courierProfile'])->courierRuntimeSnapshot();

        $this->assertSame(Courier::STATUS_ASSIGNED, $runtime['status']);
        $this->assertTrue($runtime['online']);
        $this->assertTrue($runtime['busy']);
        $this->assertSame(User::SESSION_ASSIGNED, $runtime['session_state']);
    }

}
