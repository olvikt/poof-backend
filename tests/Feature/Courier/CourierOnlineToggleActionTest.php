<?php

namespace Tests\Feature\Courier;

use App\Livewire\Courier\OnlineToggle;
use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CourierOnlineToggleActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggle_action_switches_offline_to_online(): void
    {
        $courier = $this->createCourier();

        $this->actingAs($courier, 'web');

        Livewire::test(OnlineToggle::class)
            ->assertSet('online', false)
            ->assertSee('Не на лінії', false)
            ->call('toggleOnlineState')
            ->assertDispatched('courier-online-toggled', online: true, changed: true, reason: null)
            ->assertDispatched('courier:online')
            ->assertSet('online', true)
            ->assertSee('На лінії', false);

        $courier->refresh();

        $this->assertTrue($courier->isCourierOnline());
        $this->assertTrue((bool) $courier->is_online);
        $this->assertFalse((bool) $courier->is_busy);
        $this->assertSame(User::SESSION_READY, $courier->session_state);
        $this->assertSame(Courier::STATUS_ONLINE, $courier->courierProfile->status);
    }

    public function test_toggle_action_switches_online_to_offline(): void
    {
        $courier = $this->createCourier();
        $courier->goOnline();

        $this->actingAs($courier, 'web');

        Livewire::test(OnlineToggle::class)
            ->assertSet('online', true)
            ->assertSee('На лінії', false)
            ->call('toggleOnlineState')
            ->assertDispatched('courier-online-toggled', online: false, changed: true, reason: null)
            ->assertDispatched('courier:offline')
            ->assertSet('online', false)
            ->assertSee('Не на лінії', false);

        $courier->refresh();

        $this->assertFalse($courier->isCourierOnline());
        $this->assertFalse((bool) $courier->is_online);
        $this->assertFalse((bool) $courier->is_busy);
        $this->assertSame(User::SESSION_OFFLINE, $courier->session_state);
        $this->assertSame(Courier::STATUS_OFFLINE, $courier->courierProfile->status);
    }


    public function test_toggle_action_supports_repeated_bidirectional_toggles(): void
    {
        $courier = $this->createCourier();

        $this->actingAs($courier, 'web');

        $component = Livewire::test(OnlineToggle::class)
            ->assertSet('online', false)
            ->call('toggleOnlineState')
            ->assertSet('online', true)
            ->assertDispatched('courier-online-toggled', online: true, changed: true)
            ->assertDispatched('courier:online');

        $component
            ->call('toggleOnlineState')
            ->assertSet('online', false)
            ->assertDispatched('courier-online-toggled', online: false, changed: true)
            ->assertDispatched('courier:offline');

        $courier->refresh();

        $this->assertFalse($courier->isCourierOnline());
        $this->assertSame(User::SESSION_OFFLINE, $courier->session_state);
        $this->assertSame(Courier::STATUS_OFFLINE, $courier->courierProfile->status);
    }

    public function test_toggle_to_offline_is_blocked_for_accepted_active_order_and_returns_diagnostics(): void
    {
        [$courier] = $this->createCourierWithActiveOrder(Order::STATUS_ACCEPTED);

        $this->actingAs($courier, 'web');

        Livewire::test(OnlineToggle::class)
            ->assertSet('online', true)
            ->call('toggleOnlineState')
            ->assertDispatched('courier-online-toggled', online: true, changed: false, attempted_online: false, reason: 'blocked_by_active_order')
            ->assertDispatched('courier-online-toggle-blocked', attempted_online: false, reason: 'blocked_by_active_order')
            ->assertNotDispatched('courier:offline')
            ->assertSet('online', true);

        $courier->refresh();

        $this->assertTrue((bool) $courier->is_online);
        $this->assertTrue((bool) $courier->is_busy);
        $this->assertSame(User::SESSION_ASSIGNED, $courier->session_state);
        $this->assertSame(Courier::STATUS_ASSIGNED, $courier->courierProfile->status);
    }

    public function test_toggle_to_offline_is_blocked_for_in_progress_active_order_and_returns_diagnostics(): void
    {
        [$courier] = $this->createCourierWithActiveOrder(Order::STATUS_IN_PROGRESS);

        $this->actingAs($courier, 'web');

        Livewire::test(OnlineToggle::class)
            ->assertSet('online', true)
            ->call('toggleOnlineState')
            ->assertDispatched('courier-online-toggled', online: true, changed: false, attempted_online: false, reason: 'blocked_by_active_order')
            ->assertDispatched('courier-online-toggle-blocked', attempted_online: false, reason: 'blocked_by_active_order')
            ->assertNotDispatched('courier:offline')
            ->assertSet('online', true);

        $courier->refresh();

        $this->assertTrue((bool) $courier->is_online);
        $this->assertTrue((bool) $courier->is_busy);
        $this->assertSame(User::SESSION_IN_PROGRESS, $courier->session_state);
        $this->assertSame(Courier::STATUS_DELIVERING, $courier->courierProfile->status);
    }

    public function test_toggle_action_recovers_legacy_paused_status_and_switches_online(): void
    {
        $courier = $this->createCourier();
        $courier->courierProfile()->update(['status' => Courier::STATUS_PAUSED]);

        $this->actingAs($courier, 'web');

        Livewire::test(OnlineToggle::class)
            ->assertSet('online', false)
            ->call('toggleOnlineState')
            ->assertDispatched('courier-online-toggled', online: true, changed: true, reason: null)
            ->assertSet('online', true);

        $courier->refresh();

        $this->assertTrue($courier->isCourierOnline());
        $this->assertTrue((bool) $courier->is_online);
        $this->assertFalse((bool) $courier->is_busy);
        $this->assertSame(User::SESSION_READY, $courier->session_state);
        $this->assertSame(Courier::STATUS_ONLINE, $courier->courierProfile->status);
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
            'address_text' => 'вул. Активна, 11',
            'price' => 100,
            'accepted_at' => now(),
            'started_at' => $orderStatus === Order::STATUS_IN_PROGRESS ? now() : null,
        ]);

        $courier->refresh();

        return [$courier];
    }
}
