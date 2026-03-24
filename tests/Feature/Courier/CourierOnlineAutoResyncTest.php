<?php

namespace Tests\Feature\Courier;

use App\Jobs\MarkInactiveCouriers;
use App\Livewire\Courier\AvailableOrders;
use App\Livewire\Courier\MyOrders;
use App\Livewire\Courier\OnlineToggle;
use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CourierOnlineAutoResyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_orders_screen_shows_offline_overlay_after_ttl_on_refresh_tick(): void
    {
        $courier = $this->createCourier(online: true, lastLocationAt: now()->subSeconds(121));

        $this->actingAs($courier, 'web');

        $component = Livewire::test(AvailableOrders::class)
            ->assertSet('online', true)
            ->assertDontSee('Ви не на лінії');

        (new MarkInactiveCouriers())->handle();

        $component
            ->call('$refresh')
            ->assertSet('online', false)
            ->assertSee('Ви не на лінії');
    }

    public function test_header_toggle_resyncs_with_server_offline_state_after_ttl(): void
    {
        $courier = $this->createCourier(online: true, lastLocationAt: now()->subSeconds(121));

        $this->actingAs($courier, 'web');

        $component = Livewire::test(OnlineToggle::class)
            ->assertSet('online', true)
            ->assertSee('🟢 На лінії', false);

        (new MarkInactiveCouriers())->handle();

        $component
            ->call('syncOnlineState')
            ->assertSet('online', false)
            ->assertSee('⚫ Не на лінії', false);
    }

    public function test_my_orders_actions_become_disabled_after_auto_offline_resync(): void
    {
        [$courier, $order] = $this->createCourierWithAcceptedOrder(lastLocationAt: now()->subSeconds(121));

        $this->actingAs($courier, 'web');

        $component = Livewire::test(MyOrders::class)
            ->assertSet('online', true)
            ->assertSee("wire:click=\"start({$order->id})\"", false)
            ->assertDontSee('opacity-40 pointer-events-none', false);

        (new MarkInactiveCouriers())->handle();

        $component
            ->call('$refresh')
            ->assertSet('online', false)
            ->assertSee("wire:click=\"start({$order->id})\"", false)
            ->assertSee('opacity-40 pointer-events-none', false);
    }

    private function createCourier(bool $online = false, $lastLocationAt = null): User
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

        if ($online) {
            $courier->goOnline();
        }

        if ($lastLocationAt !== null) {
            $courier->courierProfile()->update([
                'last_location_at' => $lastLocationAt,
            ]);
        }

        return $courier->fresh(['courierProfile']);
    }

    private function createCourierWithAcceptedOrder($lastLocationAt = null): array
    {
        $courier = $this->createCourier(online: true, lastLocationAt: $lastLocationAt);
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_ACCEPTED,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'вул. Тестова, 10',
            'price' => 150,
            'accepted_at' => now(),
            'lat' => 48.4647,
            'lng' => 35.0462,
        ]);

        return [$courier->fresh(['courierProfile']), $order];
    }
}
