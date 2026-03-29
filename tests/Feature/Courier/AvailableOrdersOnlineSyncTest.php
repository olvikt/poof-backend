<?php

namespace Tests\Feature\Courier;

use App\Livewire\Courier\AvailableOrders;
use App\Models\Courier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_polling_self_heals_stale_online_event_back_to_canonical_state_after_grace_window(): void
    {
        Carbon::setTestNow(now());

        try {
            $courier = $this->createCourier();

            $this->actingAs($courier, 'web');

            $component = Livewire::test(AvailableOrders::class)
                ->assertSet('online', false)
                ->dispatch('courier-online-toggled', online: true)
                ->assertSet('online', true);

            Carbon::setTestNow(now()->addSeconds(5));

            $component
                ->call('$refresh')
                ->assertSet('online', false)
                ->assertSee('Ви не на лінії');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_optimistic_online_event_is_kept_within_grace_window(): void
    {
        Carbon::setTestNow(now());

        try {
            $courier = $this->createCourier();

            $this->actingAs($courier, 'web');

            $component = Livewire::test(AvailableOrders::class)
                ->assertSet('online', false)
                ->dispatch('courier-online-toggled', online: true)
                ->assertSet('online', true);

            Carbon::setTestNow(now()->addSeconds(2));

            $component
                ->call('$refresh')
                ->assertSet('online', true);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_explicit_sync_without_payload_prioritizes_backend_truth_over_optimistic_projection(): void
    {
        $courier = $this->createCourier();

        $this->actingAs($courier, 'web');

        Livewire::test(AvailableOrders::class)
            ->assertSet('online', false)
            ->dispatch('courier-online-toggled', online: true)
            ->assertSet('online', true)
            ->call('syncOnlineState')
            ->assertSet('online', false);
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
