<?php

namespace Tests\Feature\Courier;

use App\Livewire\Courier\AvailableOrders;
use App\Livewire\Courier\MyOrders;
use App\Livewire\Courier\OnlineToggle;
use App\Models\Courier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CourierOnlineNavigationSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_online_state_stays_consistent_between_available_and_my_orders_screens(): void
    {
        $courier = $this->createCourier();

        $this->actingAs($courier, 'web');

        Livewire::test(OnlineToggle::class)
            ->call('toggleOnlineState')
            ->assertSet('online', true)
            ->assertDispatched('courier-online-toggled', online: true);

        Livewire::test(AvailableOrders::class)
            ->assertSet('online', true)
            ->assertDontSee('Ви не на лінії');

        Livewire::test(MyOrders::class)
            ->assertSet('online', true);

        Livewire::test(OnlineToggle::class)
            ->assertSet('online', true)
            ->assertSee('🟢 На лінії', false);

        $this->get(route('courier.orders'))
            ->assertOk()
            ->assertSee('🟢 На лінії', false);

        $this->get(route('courier.my-orders'))
            ->assertOk()
            ->assertSee('🟢 На лінії', false);

        $courier->refresh();

        $this->assertTrue($courier->isCourierOnline());
        $this->assertTrue((bool) $courier->is_online);
        $this->assertSame(Courier::STATUS_ONLINE, $courier->courierProfile->status);
    }


    public function test_toggle_action_remains_available_after_switching_between_courier_tabs(): void
    {
        $courier = $this->createCourier();

        $this->actingAs($courier, 'web');

        $this->get(route('courier.orders'))
            ->assertOk()
            ->assertSee('wire:click="toggleOnlineState"', false);

        Livewire::test(OnlineToggle::class)
            ->call('toggleOnlineState')
            ->assertSet('online', true);

        $this->get(route('courier.my-orders'))
            ->assertOk()
            ->assertSee('wire:click="toggleOnlineState"', false)
            ->assertSee('🟢 На лінії', false);

        Livewire::test(OnlineToggle::class)
            ->call('toggleOnlineState')
            ->assertSet('online', false);

        $courier->refresh();

        $this->assertFalse((bool) $courier->is_online);
        $this->assertSame(Courier::STATUS_OFFLINE, $courier->courierProfile->status);
        $this->assertSame(User::SESSION_OFFLINE, $courier->session_state);
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
