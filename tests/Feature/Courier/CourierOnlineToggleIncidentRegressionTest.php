<?php

namespace Tests\Feature\Courier;

use App\Jobs\MarkInactiveCouriers;
use App\Livewire\Courier\AvailableOrders;
use App\Livewire\Courier\LocationTracker;
use App\Livewire\Courier\MyOrders;
use App\Livewire\Courier\OnlineToggle;
use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\TestCase;

class CourierOnlineToggleIncidentRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_clicking_online_toggle_persists_canonical_online_state_and_hydrate_does_not_revert(): void
    {
        Log::spy();
        config()->set('courier_runtime.incident_logging.enabled', true);

        $courier = $this->createCourier();
        $this->actingAs($courier, 'web');

        Livewire::test(OnlineToggle::class)
            ->assertSet('online', false)
            ->call('toggleOnlineState')
            ->assertSet('online', true)
            ->call('hydrate')
            ->assertSet('online', true);

        $courier->refresh();

        $this->assertSame(Courier::STATUS_ONLINE, $courier->courierProfile->status);
        $this->assertTrue((bool) $courier->is_online);
        $this->assertFalse((bool) $courier->is_busy);
        $this->assertSame(User::SESSION_READY, $courier->session_state);
        $this->assertNotNull($courier->courierProfile->last_location_at);

        Log::shouldHaveReceived('info')->withArgs(fn (string $message): bool => $message === 'online_toggle_requested')->once();
        Log::shouldHaveReceived('info')->withArgs(fn (string $message): bool => $message === 'online_toggle_persisted')->once();
        Log::shouldHaveReceived('info')->withArgs(fn (string $message): bool => $message === 'online_toggle_snapshot_after_write')->once();
        Log::shouldHaveReceived('info')->withArgs(fn (string $message): bool => $message === 'online_toggle_snapshot_after_hydrate')->atLeast()->once();
    }

    public function test_stale_sweeper_does_not_immediately_revert_fresh_online_toggle_without_heartbeat(): void
    {
        $courier = $this->createCourier();

        $this->actingAs($courier, 'web');

        Livewire::test(OnlineToggle::class)
            ->call('toggleOnlineState')
            ->assertSet('online', true);

        (new MarkInactiveCouriers())->handle();

        $courier->refresh();

        $this->assertSame(Courier::STATUS_ONLINE, $courier->courierProfile->status);
        $this->assertTrue((bool) $courier->is_online);
    }

    public function test_background_runtime_sync_path_cannot_overwrite_fresh_toggle_with_stale_signal(): void
    {
        $courier = $this->createCourier();
        $this->actingAs($courier, 'web');

        Livewire::test(OnlineToggle::class)
            ->call('toggleOnlineState')
            ->assertSet('online', true);

        Livewire::test(AvailableOrders::class)
            ->dispatch('courier-online-toggled', online: false, changed: false, reason: 'courier_runtime_sync')
            ->assertSet('online', true);

        Livewire::test(MyOrders::class)
            ->dispatch('courier-online-toggled', online: false, changed: false, reason: 'courier_runtime_sync')
            ->assertSet('online', true);
    }

    public function test_location_tracker_runtime_sync_does_not_force_offline_after_successful_toggle_and_logs_payload(): void
    {
        Log::spy();
        config()->set('courier_runtime.incident_logging.enabled', true);

        $courier = $this->createCourier();
        $this->actingAs($courier, 'web');

        Livewire::test(OnlineToggle::class)
            ->call('toggleOnlineState')
            ->assertSet('online', true);

        Livewire::test(LocationTracker::class)
            ->assertDispatched('courier:runtime-sync', online: true, status: Courier::STATUS_ONLINE);

        $courier->refresh();
        $this->assertTrue($courier->isCourierOnline());

        Log::shouldHaveReceived('info')->withArgs(fn (string $message): bool => $message === 'runtime_sync_event_emitted')->once();
        Log::shouldHaveReceived('info')->withArgs(fn (string $message): bool => $message === 'runtime_sync_event_payload')->once();
    }

    public function test_offline_toggle_with_active_order_is_explicitly_blocked_and_reports_guard_reason(): void
    {
        Log::spy();
        config()->set('courier_runtime.incident_logging.enabled', true);

        [$courier] = $this->createCourierWithActiveOrder(Order::STATUS_ACCEPTED);
        $this->actingAs($courier, 'web');

        Livewire::test(OnlineToggle::class)
            ->call('toggleOnlineState')
            ->assertDispatched('courier-online-toggle-blocked', reason: 'blocked_by_active_order')
            ->assertSet('online', true);

        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
            return $message === 'forced_repair_or_guard_reason'
                && ($context['reason'] ?? null) === 'blocked_by_active_order';
        })->once();
    }

    private function createCourier(): User
    {
        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_busy' => false,
            'is_online' => false,
            'session_state' => User::SESSION_OFFLINE,
        ]);

        Courier::query()->create([
            'user_id' => $courier->id,
            'status' => Courier::STATUS_OFFLINE,
            'last_location_at' => null,
        ]);

        return $courier;
    }

    private function createCourierWithActiveOrder(string $orderStatus): array
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $courier = $this->createCourier();
        $courier->goOnline();

        Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => $orderStatus,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'вул. Інцидентна, 7',
            'price' => 100,
            'accepted_at' => now(),
        ]);

        $courier->refresh();

        return [$courier];
    }
}
