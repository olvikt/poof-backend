<?php

namespace Tests\Feature\Courier;

use App\Livewire\Courier\AvailableOrders;
use App\Models\Courier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AvailableOrdersOnlineSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_online_toggle_event_updates_available_orders_and_hides_offline_overlay(): void
    {
        $courier = $this->createCourier();

        $this->actingAs($courier, 'web');

        Livewire::test(AvailableOrders::class)
            ->assertSet('online', false)
            ->assertSee('Ви не на лінії')
            ->dispatch('courier-online-toggled', online: true)
            ->assertSet('online', true)
            ->call('$refresh')
            ->assertSet('online', true)
            ->assertDontSee('Ви не на лінії');
    }

    public function test_sync_online_state_can_refresh_from_canonical_user_state(): void
    {
        $courier = $this->createCourier();

        $this->actingAs($courier, 'web');

        $component = Livewire::test(AvailableOrders::class)
            ->assertSet('online', false);

        $courier->goOnline();

        $component
            ->call('syncOnlineState')
            ->assertSet('online', true)
            ->assertDontSee('Ви не на лінії');
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
