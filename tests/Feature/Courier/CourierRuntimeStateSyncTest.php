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
use Tests\TestCase;

class CourierRuntimeStateSyncTest extends TestCase
{
    use RefreshDatabase;

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

        $order = Order::query()->create([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address' => 'вул. Тестова, 1',
            'address_text' => 'вул. Тестова, 1',
            'price' => 100,
        ]);

        $this->assertTrue($order->acceptBy($courier));
        $courier->refresh();
        $this->assertSame(Courier::STATUS_ASSIGNED, $courier->courierProfile->status);
        $this->assertTrue((bool) $courier->is_busy);

        $this->assertTrue($order->fresh()->startBy($courier));
        $courier->refresh();
        $this->assertSame(Courier::STATUS_DELIVERING, $courier->courierProfile->status);
        $this->assertTrue((bool) $courier->is_busy);

        $this->assertTrue($order->fresh()->completeBy($courier));
        $courier->refresh();
        $this->assertSame(Courier::STATUS_ONLINE, $courier->courierProfile->status);
        $this->assertFalse((bool) $courier->is_busy);
        $this->assertSame(User::SESSION_READY, $courier->session_state);
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
        $this->assertSame(User::SESSION_IN_PROGRESS, $courier->session_state);
    }

    public function test_active_order_prevents_forced_offline_transition(): void
    {
        [$courier] = $this->createCourierWithActiveOrder(Order::STATUS_ACCEPTED);

        $courier->goOffline(force: true);
        $courier->refresh();

        $this->assertSame(Courier::STATUS_ASSIGNED, $courier->courierProfile->status);
        $this->assertTrue((bool) $courier->is_busy);
        $this->assertTrue((bool) $courier->is_online);
        $this->assertSame(User::SESSION_IN_PROGRESS, $courier->session_state);
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
        $this->assertSame(User::SESSION_IN_PROGRESS, $courier->session_state);
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
        $this->assertSame(User::SESSION_IN_PROGRESS, $busyCourier->session_state);

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

        Livewire::test(LocationTracker::class);

        $courier->refresh();

        $this->assertSame(Courier::STATUS_ASSIGNED, $courier->courierProfile->status);
        $this->assertTrue((bool) $courier->is_busy);
        $this->assertTrue((bool) $courier->is_online);
        $this->assertSame(User::SESSION_IN_PROGRESS, $courier->session_state);
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

        $order = Order::query()->create([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address' => 'вул. Тестова, 2',
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

        $order = Order::query()->create([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => $orderStatus,
            'payment_status' => Order::PAY_PAID,
            'address' => 'вул. Активна, 11',
            'address_text' => 'вул. Активна, 11',
            'price' => 100,
            'accepted_at' => now(),
        ]);

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
