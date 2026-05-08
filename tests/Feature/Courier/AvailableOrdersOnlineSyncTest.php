<?php

namespace Tests\Feature\Courier;

use App\Livewire\Courier\AvailableOrders;
use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderOffer;
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
            ->assertSee('Ви зараз офлайн')
            ->dispatch('courier-online-toggled', online: true, changed: true)
            ->assertSet('online', true)
            ->call('$refresh')
            ->assertSet('online', true)
            ->assertDontSee('Ви зараз офлайн');
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
                ->dispatch('courier-online-toggled', online: true, changed: true)
                ->assertSet('online', true);

            Carbon::setTestNow(now()->addSeconds(5));

            $component
                ->call('$refresh')
                ->assertSet('online', false)
                ->assertSee('Ви зараз офлайн');
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
                ->dispatch('courier-online-toggled', online: true, changed: true)
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
            ->dispatch('courier-online-toggled', online: true, changed: true)
            ->assertSet('online', true)
            ->call('syncOnlineState')
            ->assertSet('online', false);
    }

    public function test_non_changed_event_payload_does_not_override_canonical_backend_state(): void
    {
        $courier = $this->createCourier();

        $this->actingAs($courier, 'web');

        Livewire::test(AvailableOrders::class)
            ->assertSet('online', false)
            ->dispatch('courier-online-toggled', online: true, changed: false, reason: 'cross_tab_runtime_sync')
            ->assertSet('online', false)
            ->assertSee('Ви зараз офлайн');
    }

    public function test_online_courier_with_future_nearby_orders_sees_soon_hint(): void
    {
        $courier = $this->createCourier();
        $courier->forceFill(['last_lat' => 50.45, 'last_lng' => 30.52])->save();
        $courier->goOnline();
        $courier->courierProfile()->update(['last_location_at' => now()]);

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'soon',
            'lat' => 50.451,
            'lng' => 30.521,
            'scheduled_date' => now()->toDateString(),
            'dispatch_available_at' => now()->addMinutes(20),
            'price' => 120,
        ]);

        $this->actingAs($courier, 'web');

        Livewire::test(AvailableOrders::class)
            ->assertSee('У вашому районі є 1 замовлень, вони скоро стануть доступні')
            ->assertSee('Найближче:');
    }

    public function test_active_pending_offer_hides_empty_state(): void
    {
        $courier = $this->createCourier();
        $courier->forceFill(['last_lat' => 50.45, 'last_lng' => 30.52])->save();
        $courier->goOnline();
        $courier->courierProfile()->update(['last_location_at' => now()]);

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'offer',
            'price' => 120,
        ]);
        OrderOffer::query()->create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'status' => OrderOffer::STATUS_PENDING,
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->actingAs($courier, 'web');

        Livewire::test(AvailableOrders::class)
            ->assertDontSee('Зараз доступних замовлень немає')
            ->assertDontSee('Очікуємо вашу геолокацію');
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
