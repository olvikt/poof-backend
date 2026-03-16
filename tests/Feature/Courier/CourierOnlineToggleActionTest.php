<?php

namespace Tests\Feature\Courier;

use App\Livewire\Courier\OnlineToggle;
use App\Models\Courier;
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
            ->assertSee('⚫ Не на лінії', false)
            ->call('toggleOnlineState')
            ->assertDispatched('courier-online-toggled', online: true)
            ->assertDispatched('courier:online')
            ->assertSet('online', true)
            ->assertSee('🟢 На лінії', false);

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
            ->assertSee('🟢 На лінії', false)
            ->call('toggleOnlineState')
            ->assertDispatched('courier-online-toggled', online: false)
            ->assertDispatched('courier:offline')
            ->assertSet('online', false)
            ->assertSee('⚫ Не на лінії', false);

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
            ->assertDispatched('courier-online-toggled', online: true)
            ->assertDispatched('courier:online');

        $component
            ->call('toggleOnlineState')
            ->assertSet('online', false)
            ->assertDispatched('courier-online-toggled', online: false)
            ->assertDispatched('courier:offline');

        $courier->refresh();

        $this->assertFalse($courier->isCourierOnline());
        $this->assertSame(User::SESSION_OFFLINE, $courier->session_state);
        $this->assertSame(Courier::STATUS_OFFLINE, $courier->courierProfile->status);
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
}
