<?php

namespace Tests\Feature\Courier;

use App\Jobs\MarkInactiveCouriers;
use App\Livewire\Courier\LocationTracker;
use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\Concerns\BuildsOrderRuntimeFixtures;
use Tests\TestCase;

class CourierOnlineSessionStabilityTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrderRuntimeFixtures;

    public function test_online_session_survives_switching_between_courier_tabs(): void
    {
        $courier = $this->createCourier();

        $courier->goOnline();

        $this->actingAs($courier, 'web')->get(route('courier.my-orders'))->assertOk();
        $this->actingAs($courier, 'web')->get(route('courier.orders'))->assertOk();
        $this->actingAs($courier, 'web')->get(route('courier.my-orders'))->assertOk();

        $courier->refresh();

        $this->assertTrue($courier->isCourierOnline());
        $this->assertTrue((bool) $courier->is_online);
        $this->assertSame(Courier::STATUS_ONLINE, $courier->courierProfile->status);
    }

    public function test_fresh_location_timestamp_keeps_courier_online_during_inactive_check(): void
    {
        $courier = $this->createCourier();

        $courier->goOnline();
        $courier->courierProfile()->update([
            'last_location_at' => now()->subSeconds(30),
        ]);

        (new MarkInactiveCouriers())->handle();

        $courier->refresh();

        $this->assertTrue($courier->isCourierOnline());
        $this->assertTrue((bool) $courier->is_online);
        $this->assertSame(Courier::STATUS_ONLINE, $courier->courierProfile->status);
    }

    public function test_ttl_marks_courier_offline_without_fresh_location(): void
    {
        $courier = $this->createCourier();

        $courier->goOnline();
        $courier->courierProfile()->update([
            'last_location_at' => now()->subSeconds(121),
        ]);

        (new MarkInactiveCouriers())->handle();

        $courier->refresh();

        $this->assertFalse($courier->isCourierOnline());
        $this->assertFalse((bool) $courier->is_online);
        $this->assertSame(Courier::STATUS_OFFLINE, $courier->courierProfile->status);
    }

    public function test_heartbeat_with_accuracy_110_is_accepted_and_prevents_premature_offline(): void
    {
        config()->set('courier_runtime.heartbeat.max_accuracy_meters', 120.0);

        $courier = $this->createCourier();
        $courier->goOnline();

        $this->actingAs($courier, 'web');

        Livewire::test(LocationTracker::class)
            ->call('updateLocation', 48.4647, 35.0462, 110);

        $courier->refresh();

        $this->assertNotNull($courier->courierProfile->last_location_at);
        $this->assertTrue((bool) $courier->is_online);

        (new MarkInactiveCouriers())->handle();

        $courier->refresh();

        $this->assertTrue($courier->isCourierOnline());
        $this->assertSame(Courier::STATUS_ONLINE, $courier->courierProfile->status);
    }

    public function test_stale_sweeper_logs_forced_offline_reason(): void
    {
        Log::spy();

        $courier = $this->createCourier();
        $courier->goOnline();
        $courier->courierProfile()->update([
            'last_location_at' => now()->subSeconds(121),
        ]);

        (new MarkInactiveCouriers())->handle();

        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
            return $message === 'courier_forced_offline_stale_location'
                && ($context['reason'] ?? null) === 'stale_location_ttl_expired'
                && isset($context['courier_id']);
        })->once();
    }

    public function test_location_tracker_diagnostic_log_identifies_heartbeat_receipt(): void
    {
        Log::spy();
        config()->set('courier_runtime.heartbeat.diagnostic_logging', true);

        $courier = $this->createCourier();
        $courier->goOnline();

        $this->actingAs($courier, 'web');

        Livewire::test(LocationTracker::class)
            ->call('updateLocation', 48.4647, 35.0462, 25);

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            return $message === 'courier_location_heartbeat_received'
                && isset($context['courier_id'])
                && array_key_exists('last_location_at', $context);
        })->once();
    }

    public function test_active_order_with_stale_offline_raw_status_is_synced_by_explicit_location_ingest_boundary(): void
    {
        Log::spy();
        [$courier, $order] = $this->createCourierWithAcceptedOrder();

        $courier->courierProfile()->update([
            'status' => Courier::STATUS_OFFLINE,
            'last_location_at' => now()->subMinutes(5),
        ]);
        $courier->update([
            'last_lat' => 50.4501,
            'last_lng' => 30.5234,
        ]);

        // Canonical read stays pure before ingest boundary write.
        $this->assertSame(Courier::STATUS_ASSIGNED, $courier->fresh()->courierRuntimeState());
        $this->assertSame(Courier::STATUS_OFFLINE, $courier->fresh()->courierProfile->status);

        $this->actingAs($courier, 'web');
        Livewire::test(LocationTracker::class)
            ->call('updateLocation', 48.4647, 35.0462, 20);

        $courier->refresh();
        $order->refresh();

        $this->assertSame(Order::STATUS_ACCEPTED, $order->status);
        $this->assertSame(Courier::STATUS_ASSIGNED, $courier->courierProfile->status);
        $this->assertNotNull($courier->courierProfile->last_location_at);
        $this->assertSame(Courier::STATUS_ASSIGNED, $courier->courierRuntimeState());

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'web')
            ->getJson('/api/admin/map-data')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $courier->courierProfile->id,
                'status' => Courier::STATUS_ASSIGNED,
            ]);

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            return $message === 'location_ingest_status_sync_boundary'
                && ($context['counter'] ?? null) === 'location_ingest_status_sync_total'
                && ($context['counter_labels']['reason'] ?? null) === 'active_order_status_enforced';
        })->once();
    }

    public function test_offline_free_courier_heartbeat_does_not_silently_promote_online(): void
    {
        $courier = $this->createCourier();
        $this->actingAs($courier, 'web');

        Livewire::test(LocationTracker::class)
            ->call('updateLocation', 48.4647, 35.0462, 20);

        $courier->refresh();
        $this->assertSame(Courier::STATUS_OFFLINE, $courier->courierProfile->status);
        $this->assertNull($courier->courierProfile->last_location_at);
        $this->assertFalse($courier->isCourierOnline());
    }

    public function test_runtime_sync_payload_remains_non_authoritative_against_canonical_backend_truth(): void
    {
        $courier = $this->createCourier();

        $this->actingAs($courier, 'web');
        $component = Livewire::test(LocationTracker::class);

        $component->dispatch('courier:runtime-sync', online: true, status: Courier::STATUS_ONLINE);

        $courier->refresh();
        $this->assertSame(Courier::STATUS_OFFLINE, $courier->courierProfile->status);
        $this->assertFalse($courier->isCourierOnline());
    }

    private function createCourier(): User
    {
        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_busy' => false,
            'is_online' => false,
        ]);

        Courier::query()->create([
            'user_id' => $courier->id,
            'status' => Courier::STATUS_OFFLINE,
            'last_location_at' => null,
        ]);

        return $courier;
    }

    /**
     * @return array{User,Order}
     */
    private function createCourierWithAcceptedOrder(): array
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $courier = $this->createCourier();
        $courier->goOnline();
        $order = $this->createAcceptedOrderAssignedToCourier($client, $courier);

        return [$courier->fresh(), $order->fresh()];
    }
}
