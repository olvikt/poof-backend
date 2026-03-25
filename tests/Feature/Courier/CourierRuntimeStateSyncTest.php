<?php

namespace Tests\Feature\Courier;

use App\Jobs\MarkInactiveCouriers;
use App\Listeners\ResetCourierSessionOnLogin;
use App\Livewire\Courier\LocationTracker;
use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use App\Services\Dispatch\OfferDispatcher;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsOrderRuntimeFixtures;
use Tests\TestCase;

class CourierRuntimeStateSyncTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrderRuntimeFixtures;

    public function test_go_online_sets_unified_online_state(): void
    {
        $courier = $this->createCourier(Courier::STATUS_OFFLINE);

        $courier->goOnline();
        $courier->refresh();

        $this->assertSame(Courier::STATUS_ONLINE, $courier->courierProfile->status);
        $this->assertTrue((bool) $courier->is_online);
        $this->assertFalse((bool) $courier->is_busy);
        $this->assertSame(User::SESSION_READY, $courier->session_state);
    }

    public function test_go_offline_sets_unified_offline_state(): void
    {
        $courier = $this->createCourier(Courier::STATUS_ONLINE);

        $courier->goOffline();
        $courier->refresh();

        $this->assertSame(Courier::STATUS_OFFLINE, $courier->courierProfile->status);
        $this->assertFalse((bool) $courier->is_online);
        $this->assertFalse((bool) $courier->is_busy);
        $this->assertSame(User::SESSION_OFFLINE, $courier->session_state);
    }

    public function test_order_lifecycle_keeps_runtime_fields_in_sync(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createCourier(Courier::STATUS_ONLINE);

        $order = $this->createDispatchableSearchingPaidOrder($client, [
            'address_text' => 'вул. Тестова, 1',
            'price' => 100,
        ]);

        $this->assertTrue($order->acceptBy($courier));
        $courier->refresh();
        $order->refresh();

        $this->assertSame(Order::STATUS_ACCEPTED, $order->status);
        $this->assertNull($order->started_at);
        $this->assertSame(Courier::STATUS_ASSIGNED, $courier->courierProfile->status);
        $this->assertTrue((bool) $courier->is_busy);
        $this->assertSame(User::SESSION_ASSIGNED, $courier->session_state);

        $this->assertTrue($order->fresh()->startBy($courier));
        $courier->refresh();
        $order->refresh();

        $this->assertSame(Order::STATUS_IN_PROGRESS, $order->status);
        $this->assertNotNull($order->started_at);
        $this->assertSame(Courier::STATUS_DELIVERING, $courier->courierProfile->status);
        $this->assertTrue((bool) $courier->is_busy);
        $this->assertSame(User::SESSION_IN_PROGRESS, $courier->session_state);

        $this->assertTrue($order->fresh()->completeBy($courier));
        $courier->refresh();
        $order->refresh();

        $this->assertSame(Order::STATUS_DONE, $order->status);
        $this->assertNotNull($order->completed_at);
        $this->assertSame(Courier::STATUS_ONLINE, $courier->courierProfile->status);
        $this->assertFalse((bool) $courier->is_busy);
        $this->assertSame(User::SESSION_READY, $courier->session_state);
    }

    public function test_cancel_from_accepted_restores_free_online_runtime_state(): void
    {
        [$courier, $order] = $this->createCourierWithActiveOrder(Order::STATUS_ACCEPTED);

        $courier->markBusy();

        $this->assertTrue($order->fresh()->cancel());

        $courier->refresh();
        $order->refresh();

        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertSame(Courier::STATUS_ONLINE, $courier->courierProfile->status);
        $this->assertTrue((bool) $courier->is_online);
        $this->assertFalse((bool) $courier->is_busy);
        $this->assertSame(User::SESSION_READY, $courier->session_state);
        $this->assertFalse($courier->hasActiveCourierOrder());
    }

    public function test_accepted_fixture_builder_keeps_assigned_runtime_contract(): void
    {
        [$courier, $order] = $this->createCourierWithActiveOrder(Order::STATUS_ACCEPTED);

        $this->assertSame(Order::STATUS_ACCEPTED, $order->status);
        $this->assertSame(Courier::STATUS_ASSIGNED, $courier->courierProfile->status);
        $this->assertSame(User::SESSION_ASSIGNED, $courier->session_state);
        $this->assertTrue((bool) $courier->is_busy);
        $this->assertTrue((bool) $courier->is_online);
    }

    public function test_in_progress_fixture_builder_keeps_delivering_runtime_contract(): void
    {
        [$courier, $order] = $this->createCourierWithActiveOrder(Order::STATUS_IN_PROGRESS);

        $this->assertSame(Order::STATUS_IN_PROGRESS, $order->status);
        $this->assertSame(Courier::STATUS_DELIVERING, $courier->courierProfile->status);
        $this->assertSame(User::SESSION_IN_PROGRESS, $courier->session_state);
        $this->assertTrue((bool) $courier->is_busy);
        $this->assertTrue((bool) $courier->is_online);
    }

    public function test_cancel_from_in_progress_is_blocked_without_mutating_runtime_state(): void
    {
        [$courier, $order] = $this->createCourierWithActiveOrder(Order::STATUS_IN_PROGRESS);

        $courier->markDelivering();

        $this->assertFalse($order->fresh()->cancel());

        $courier->refresh();
        $order->refresh();

        $this->assertSame(Order::STATUS_IN_PROGRESS, $order->status);
        $this->assertSame(Courier::STATUS_DELIVERING, $courier->courierProfile->status);
        $this->assertTrue((bool) $courier->is_online);
        $this->assertTrue((bool) $courier->is_busy);
        $this->assertSame(User::SESSION_IN_PROGRESS, $courier->session_state);
        $this->assertTrue($courier->hasActiveCourierOrder());
    }

    public function test_legacy_desynced_state_is_self_healed_by_runtime_api(): void
    {
        [$courier, $order] = $this->createCourierWithActiveOrder(Order::STATUS_ACCEPTED);

        $courier->update([
            'is_online' => true,
            'is_busy' => false,
            'session_state' => User::SESSION_OFFLINE,
        ]);
        $courier->courierProfile()->update(['status' => Courier::STATUS_OFFLINE]);

        $this->assertSame(Courier::STATUS_ASSIGNED, $courier->fresh()->courierRuntimeState());

        $courier->refresh();
        $order->refresh();

        $this->assertSame(Order::STATUS_ACCEPTED, $order->status);
        $this->assertSame(Courier::STATUS_ASSIGNED, $courier->courierProfile->status);
        $this->assertTrue((bool) $courier->is_busy);
        $this->assertTrue((bool) $courier->is_online);
        $this->assertSame(User::SESSION_ASSIGNED, $courier->session_state);
    }

    public function test_active_order_prevents_forced_offline_transition(): void
    {
        [$courier] = $this->createCourierWithActiveOrder(Order::STATUS_ACCEPTED);

        $courier->goOffline(force: true);
        $courier->refresh();

        $this->assertSame(Courier::STATUS_ASSIGNED, $courier->courierProfile->status);
        $this->assertTrue((bool) $courier->is_busy);
        $this->assertTrue((bool) $courier->is_online);
        $this->assertSame(User::SESSION_ASSIGNED, $courier->session_state);
    }

    public function test_login_reset_does_not_break_busy_state_for_active_order(): void
    {
        [$courier] = $this->createCourierWithActiveOrder(Order::STATUS_ACCEPTED);

        $courier->update([
            'is_online' => true,
            'is_busy' => false,
            'session_state' => User::SESSION_OFFLINE,
        ]);
        $courier->courierProfile()->update(['status' => Courier::STATUS_OFFLINE]);

        (new ResetCourierSessionOnLogin())->handle(new Login('web', $courier, false));

        $courier->refresh();

        $this->assertSame(Courier::STATUS_ASSIGNED, $courier->courierProfile->status);
        $this->assertTrue((bool) $courier->is_online);
        $this->assertTrue((bool) $courier->is_busy);
        $this->assertSame(User::SESSION_ASSIGNED, $courier->session_state);
    }

    public function test_login_reset_uses_offline_for_free_courier(): void
    {
        $courier = $this->createCourier(Courier::STATUS_ONLINE, isBusy: false, isOnline: true);

        (new ResetCourierSessionOnLogin())->handle(new Login('web', $courier, false));

        $courier->refresh();

        $this->assertSame(Courier::STATUS_OFFLINE, $courier->courierProfile->status);
        $this->assertFalse((bool) $courier->is_online);
        $this->assertFalse((bool) $courier->is_busy);
        $this->assertSame(User::SESSION_OFFLINE, $courier->session_state);
    }

    public function test_ttl_job_self_heals_active_order_and_offlines_only_stale_free_courier(): void
    {
        [$busyCourier] = $this->createCourierWithActiveOrder(Order::STATUS_ACCEPTED);

        $busyCourier->update([
            'is_online' => true,
            'is_busy' => false,
            'session_state' => User::SESSION_OFFLINE,
        ]);
        $busyCourier->courierProfile()->update([
            'status' => Courier::STATUS_OFFLINE,
            'last_location_at' => now()->subMinutes(10),
        ]);

        $freeCourier = $this->createCourier(
            Courier::STATUS_ONLINE,
            isBusy: false,
            isOnline: true,
            lastLocationAt: now()->subMinutes(10)
        );

        (new MarkInactiveCouriers())->handle();

        $busyCourier->refresh();
        $freeCourier->refresh();

        $this->assertSame(Courier::STATUS_ASSIGNED, $busyCourier->courierProfile->status);
        $this->assertTrue((bool) $busyCourier->is_busy);
        $this->assertTrue((bool) $busyCourier->is_online);
        $this->assertSame(User::SESSION_ASSIGNED, $busyCourier->session_state);

        $this->assertSame(Courier::STATUS_OFFLINE, $freeCourier->courierProfile->status);
        $this->assertFalse((bool) $freeCourier->is_busy);
        $this->assertFalse((bool) $freeCourier->is_online);
        $this->assertSame(User::SESSION_OFFLINE, $freeCourier->session_state);
    }

    public function test_location_tracker_mount_repairs_legacy_desync_for_active_order(): void
    {
        [$courier] = $this->createCourierWithActiveOrder(Order::STATUS_ACCEPTED);

        $courier->update([
            'is_online' => true,
            'is_busy' => false,
            'session_state' => User::SESSION_OFFLINE,
            'last_lat' => 50.4501,
            'last_lng' => 30.5234,
        ]);
        $courier->courierProfile()->update(['status' => Courier::STATUS_OFFLINE]);

        $this->actingAs($courier, 'web');

        Livewire::test(LocationTracker::class)
            ->assertDispatched('courier:runtime-sync')
            ->assertDispatched('courier:runtime-sync', online: true, status: Courier::STATUS_ASSIGNED);

        $courier->refresh();

        $this->assertSame(Courier::STATUS_ASSIGNED, $courier->courierProfile->status);
        $this->assertTrue((bool) $courier->is_busy);
        $this->assertTrue((bool) $courier->is_online);
        $this->assertSame(User::SESSION_ASSIGNED, $courier->session_state);
    }

    public function test_dispatch_uses_canonical_courier_state_for_availability(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $blockedCourier = $this->createCourier(
            Courier::STATUS_ASSIGNED,
            isBusy: false,
            isOnline: true,
            lat: 50.4501,
            lng: 30.5234,
            lastLocationAt: now()
        );

        $readyCourier = $this->createCourier(
            Courier::STATUS_ONLINE,
            isBusy: false,
            isOnline: true,
            lat: 50.4502,
            lng: 30.5235,
            lastLocationAt: now()
        );

        $order = $this->createDispatchableSearchingPaidOrder($client, [
            'address_text' => 'вул. Тестова, 2',
            'lat' => 50.4501,
            'lng' => 30.5234,
            'price' => 100,
        ]);

        $offer = app(OfferDispatcher::class)->dispatchForOrder($order);

        $this->assertNotNull($offer);
        $this->assertSame($readyCourier->id, $offer->courier_id);
        $this->assertNotSame($blockedCourier->id, $offer->courier_id);
    }

    private function createCourierWithActiveOrder(string $orderStatus): array
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createCourier(Courier::STATUS_ONLINE, isBusy: false, isOnline: true);

        $order = match ($orderStatus) {
            Order::STATUS_ACCEPTED => $this->createAcceptedOrderAssignedToCourier($client, $courier),
            Order::STATUS_IN_PROGRESS => $this->createInProgressOrderAssignedToCourier($client, $courier),
            default => throw new \InvalidArgumentException('Unsupported active order status fixture: ' . $orderStatus),
        };

        return [$courier, $order];
    }

    private function createCourier(
        string $status,
        bool $isBusy = false,
        bool $isOnline = false,
        ?float $lat = null,
        ?float $lng = null,
        $lastLocationAt = null,
    ): User {
        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_busy' => $isBusy,
            'is_online' => $isOnline,
            'session_state' => User::SESSION_OFFLINE,
            'last_lat' => $lat,
            'last_lng' => $lng,
        ]);

        Courier::query()->create([
            'user_id' => $courier->id,
            'status' => $status,
            'last_location_at' => $lastLocationAt,
        ]);

        return $courier;
    }
}
