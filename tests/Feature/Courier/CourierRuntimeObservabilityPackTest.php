<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Livewire\Courier\AvailableOrders;
use App\Livewire\Courier\LocationTracker;
use App\Livewire\Courier\MyOrders;
use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class CourierRuntimeObservabilityPackTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_snapshot_counter_increments_for_canonical_runtime_endpoint(): void
    {
        $courier = $this->createCourier();

        Log::fake();
        Sanctum::actingAs($courier);

        $this->getJson('/api/courier/runtime')->assertOk();

        Log::assertLogged('info', function (string $message, array $context): bool {
            return $message === 'courier_runtime_endpoint_observed'
                && ($context['endpoint_name'] ?? null) === 'courier_runtime_api'
                && ($context['surface_type'] ?? null) === 'api'
                && ($context['runtime_snapshot_calls'] ?? null) === 1
                && is_int($context['elapsed_ms'] ?? null);
        });
    }

    public function test_request_collector_resets_between_requests(): void
    {
        $courier = $this->createCourier();

        Log::spy();
        Sanctum::actingAs($courier);

        $this->getJson('/api/courier/runtime')->assertOk();
        $this->getJson('/api/courier/runtime')->assertOk();

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'courier_runtime_endpoint_observed'
                    && ($context['endpoint_name'] ?? null) === 'courier_runtime_api'
                    && ($context['runtime_snapshot_calls'] ?? null) === 1;
            })
            ->twice();
    }

    public function test_available_orders_and_my_orders_emit_unified_livewire_marker_with_dimensions(): void
    {
        $courier = $this->createCourier();
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'Observability',
            'price' => 100,
            'lat' => 50.4501,
            'lng' => 30.5234,
        ]);

        OrderOffer::createPrimaryPending($order->id, $courier->id, 180);

        Log::fake();
        $this->actingAs($courier, 'web');

        Livewire::test(AvailableOrders::class);
        Livewire::test(MyOrders::class);

        Log::assertLogged('info', fn (string $message, array $context): bool => $message === 'courier_runtime_endpoint_observed'
            && ($context['endpoint_name'] ?? null) === 'available_orders_render'
            && ($context['surface_type'] ?? null) === 'livewire'
            && array_key_exists('runtime_snapshot_calls', $context)
            && array_key_exists('active_order_reads', $context));

        Log::assertLogged('info', fn (string $message, array $context): bool => $message === 'courier_runtime_endpoint_observed'
            && ($context['endpoint_name'] ?? null) === 'my_orders_render'
            && ($context['surface_type'] ?? null) === 'livewire'
            && array_key_exists('runtime_pane_elapsed_ms', $context)
            && array_key_exists('stats_pane_elapsed_ms', $context));
    }

    public function test_location_tracker_emits_unified_marker_without_business_flow_changes(): void
    {
        $courier = $this->createCourier();

        Log::fake();
        $this->actingAs($courier, 'web');

        Livewire::test(LocationTracker::class);

        Log::assertLogged('info', fn (string $message, array $context): bool => $message === 'courier_runtime_endpoint_observed'
            && ($context['endpoint_name'] ?? null) === 'location_tracker_mount'
            && ($context['surface_type'] ?? null) === 'livewire');

        Log::assertLogged('info', fn (string $message, array $context): bool => $message === 'courier_runtime_endpoint_observed'
            && ($context['endpoint_name'] ?? null) === 'location_tracker_render'
            && ($context['surface_type'] ?? null) === 'livewire');
    }

    public function test_observability_instrumentation_does_not_add_writes_for_runtime_read_endpoints(): void
    {
        $courier = $this->createCourier();

        DB::flushQueryLog();
        DB::enableQueryLog();
        Sanctum::actingAs($courier);

        $this->getJson('/api/courier/runtime')->assertOk();
        $this->getJson('/api/orders/available')->assertOk();

        $writes = collect(DB::getQueryLog())
            ->filter(function (array $entry): bool {
                $query = strtolower((string) ($entry['query'] ?? ''));

                return str_starts_with($query, 'insert')
                    || str_starts_with($query, 'update')
                    || str_starts_with($query, 'delete');
            });

        $this->assertCount(0, $writes, 'Read-only courier runtime endpoints must not perform writes.');
    }

    private function createCourier(): User
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
