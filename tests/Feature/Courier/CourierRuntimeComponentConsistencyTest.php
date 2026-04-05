<?php

namespace Tests\Feature\Courier;

use App\Livewire\Courier\AvailableOrders;
use App\Livewire\Courier\MyOrders;
use App\Livewire\Courier\OnlineToggle;
use App\Models\Courier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class CourierRuntimeComponentConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_online_toggle_transition_keeps_all_livewire_components_on_canonical_online_state(): void
    {
        $courier = $this->createCourier();

        $this->actingAs($courier, 'web');

        Livewire::test(OnlineToggle::class)
            ->assertSet('online', false)
            ->call('toggleOnlineState')
            ->assertSet('online', true);

        Livewire::test(AvailableOrders::class)
            ->call('syncOnlineState')
            ->assertSet('online', true);

        Livewire::test(MyOrders::class)
            ->call('syncOnlineState')
            ->assertSet('online', true);
    }

    public function test_available_orders_optimistic_online_projection_expires_and_realigns_with_my_orders_and_toggle(): void
    {
        Carbon::setTestNow(now());

        try {
            $courier = $this->createCourier();

            $this->actingAs($courier, 'web');

            $available = Livewire::test(AvailableOrders::class)
                ->assertSet('online', false)
                ->dispatch('courier-online-toggled', online: true, changed: true)
                ->assertSet('online', true);

            Livewire::test(MyOrders::class)
                ->dispatch('courier-online-toggled', online: true, changed: true)
                ->assertSet('online', false);

            Livewire::test(OnlineToggle::class)
                ->assertSet('online', false);

            Carbon::setTestNow(now()->addSeconds(4));

            $available
                ->call('$refresh')
                ->assertSet('online', false);
        } finally {
            Carbon::setTestNow();
        }
    }

    private function createCourier(): User
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

        return $courier;
    }
}
