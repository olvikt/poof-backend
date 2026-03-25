<?php

namespace Tests\Feature\Courier;

use App\Livewire\Courier\AvailableOrders;
use App\Livewire\Courier\MyOrders;
use App\Livewire\Courier\LocationTracker;
use App\Livewire\Courier\OnlineToggle;
use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CourierOnlineNavigationSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_to_my_orders_rehydrates_online_state_from_canonical_runtime_source(): void
    {
        $courier = $this->createCourier();

        $this->actingAs($courier, 'web');

        $availableOrders = Livewire::test(AvailableOrders::class)
            ->assertSet('online', false)
            ->assertSee('Ви не на лінії')
            // Simulate a stale in-memory UI flip that can happen before canonical refresh.
            ->dispatch('courier-online-toggled', online: true)
            ->assertSet('online', true)
            ->assertDontSee('Ви не на лінії');

        $courier->refresh();
        $this->assertFalse($courier->isCourierOnline());

        Livewire::test(MyOrders::class)
            ->assertSet('online', false);

        // AvailableOrders can still be stale in-memory, but a navigation mount must not inherit it.
        $availableOrders->assertSet('online', true);

        Livewire::test(OnlineToggle::class)
            ->assertSet('online', false)
            ->assertSee('⚫ Не на лінії', false);
    }

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

        // Simulate real tab switches by mounting both page components repeatedly.
        Livewire::test(AvailableOrders::class)
            ->assertSet('online', true)
            ->assertDontSee('Ви не на лінії');

        Livewire::test(MyOrders::class)
            ->assertSet('online', true);

        $courier->refresh();

        $this->assertTrue($courier->isCourierOnline());
        $this->assertTrue((bool) $courier->is_online);
        $this->assertSame(Courier::STATUS_ONLINE, $courier->courierProfile->status);
    }


    public function test_toggle_action_remains_available_after_switching_between_courier_tabs(): void
    {
        $courier = $this->createCourier();

        $this->actingAs($courier, 'web');

        Livewire::test(AvailableOrders::class)
            ->assertSet('online', false);

        Livewire::test(OnlineToggle::class)
            ->assertSet('busyWithActiveOrder', false)
            ->assertSee('wire:click="toggleOnlineState"', false)
            ->assertDontSee('disabled aria-disabled="true"', false);

        Livewire::test(OnlineToggle::class)
            ->call('toggleOnlineState')
            ->assertSet('online', true);

        Livewire::test(MyOrders::class)
            ->assertSet('online', true);

        Livewire::test(OnlineToggle::class)
            ->assertSet('busyWithActiveOrder', false)
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

    public function test_courier_layout_exposes_wire_navigate_links_for_real_spa_tab_switches(): void
    {
        $courier = $this->createCourier();
        $this->actingAs($courier, 'web');

        $html = $this->get(route('courier.orders'))
            ->assertOk()
            ->getContent();

        $this->assertIsString($html);

        $this->assertMatchesRegularExpression(
            '/<a(?=[^>]*\bhref="' . preg_quote(route('courier.orders'), '/') . '")(?=[^>]*\bwire:navigate\b)[^>]*>/u',
            $html
        );

        $this->assertMatchesRegularExpression(
            '/<a(?=[^>]*\bhref="' . preg_quote(route('courier.my-orders'), '/') . '")(?=[^>]*\bwire:navigate\b)[^>]*>/u',
            $html
        );
    }

    public function test_busy_courier_with_accepted_order_stays_visually_online_across_tab_navigation(): void
    {
        [$courier] = $this->createCourierWithActiveOrder(Order::STATUS_ACCEPTED);

        $this->actingAs($courier, 'web');

        Livewire::test(AvailableOrders::class)
            ->assertSet('online', true);

        Livewire::test(OnlineToggle::class)
            ->assertSet('online', true)
            ->assertSet('busyWithActiveOrder', true)
            ->assertSee('🟢 На лінії', false)
            ->assertDontSee('⚫ Не на лінії', false);

        Livewire::test(MyOrders::class)
            ->assertSet('online', true);

        Livewire::test(OnlineToggle::class)
            ->assertSet('online', true)
            ->assertSet('busyWithActiveOrder', true)
            ->assertSee('🟢 На лінії', false)
            ->assertDontSee('⚫ Не на лінії', false);

        $courier->refresh();

        $this->assertTrue($courier->isCourierOnline());
        $this->assertTrue((bool) $courier->is_online);
        $this->assertSame(Courier::STATUS_ASSIGNED, $courier->courierProfile->status);
    }

    public function test_busy_courier_with_in_progress_order_stays_visually_online_across_tab_navigation(): void
    {
        [$courier] = $this->createCourierWithActiveOrder(Order::STATUS_IN_PROGRESS);

        $this->actingAs($courier, 'web');

        Livewire::test(AvailableOrders::class)
            ->assertSet('online', true);

        Livewire::test(OnlineToggle::class)
            ->assertSet('online', true)
            ->assertSet('busyWithActiveOrder', true)
            ->assertSee('🟢 На лінії', false)
            ->assertDontSee('⚫ Не на лінії', false);

        Livewire::test(MyOrders::class)
            ->assertSet('online', true);

        Livewire::test(OnlineToggle::class)
            ->assertSet('online', true)
            ->assertSet('busyWithActiveOrder', true)
            ->assertSee('🟢 На лінії', false)
            ->assertDontSee('⚫ Не на лінії', false);

        $courier->refresh();

        $this->assertTrue($courier->isCourierOnline());
        $this->assertTrue((bool) $courier->is_online);
        $this->assertSame(Courier::STATUS_DELIVERING, $courier->courierProfile->status);
    }

    public function test_free_courier_toggle_state_remains_consistent_through_tab_navigation(): void
    {
        $courier = $this->createCourier();

        $this->actingAs($courier, 'web');

        Livewire::test(AvailableOrders::class)
            ->assertSet('online', false)
            ->assertSee('Ви не на лінії');

        Livewire::test(OnlineToggle::class)
            ->assertSet('online', false)
            ->assertSee('⚫ Не на лінії', false);

        Livewire::test(OnlineToggle::class)
            ->call('toggleOnlineState')
            ->assertSet('online', true);

        Livewire::test(MyOrders::class)
            ->assertSet('online', true);

        Livewire::test(OnlineToggle::class)
            ->assertSet('online', true)
            ->assertSee('🟢 На лінії', false);

        Livewire::test(OnlineToggle::class)
            ->call('toggleOnlineState')
            ->assertSet('online', false);

        Livewire::test(AvailableOrders::class)
            ->assertSet('online', false)
            ->assertSee('Ви не на лінії');

        Livewire::test(OnlineToggle::class)
            ->assertSet('online', false)
            ->assertSee('⚫ Не на лінії', false);
    }



    public function test_available_orders_active_order_map_bootstrap_uses_active_order_coords(): void
    {
        [$courier] = $this->createCourierWithActiveOrder(Order::STATUS_ACCEPTED, [
            'last_lat' => 48.4645,
            'last_lng' => 35.0462,
            'lat' => 48.4647,
            'lng' => 35.0464,
        ]);

        $this->actingAs($courier, 'web');

        $component = Livewire::test(AvailableOrders::class);
        $mapBootstrap = $this->extractMapBootstrapFromHtml($component->html());

        $this->assertSame(48.4647, $mapBootstrap['orderLat']);
        $this->assertSame(35.0464, $mapBootstrap['orderLng']);
        $this->assertNotSame(50.4501, $mapBootstrap['orderLat']);
        $this->assertNotSame(30.5234, $mapBootstrap['orderLng']);
    }

    public function test_active_order_map_bootstrap_centers_on_order_not_default_city(): void
    {
        [$courier, $order] = $this->createCourierWithActiveOrder(Order::STATUS_ACCEPTED, [
            'last_lat' => 48.4645,
            'last_lng' => 35.0462,
            'lat' => 48.4647,
            'lng' => 35.0464,
        ]);

        $this->actingAs($courier, 'web');

        $component = Livewire::test(MyOrders::class);
        $mapBootstrap = $this->extractMapBootstrapFromHtml($component->html());

        $this->assertSame(48.4647, $mapBootstrap['orderLat']);
        $this->assertSame(35.0464, $mapBootstrap['orderLng']);
        $this->assertNotSame(50.4501, $mapBootstrap['orderLat']);
        $this->assertNotSame(30.5234, $mapBootstrap['orderLng']);
    }

    public function test_abnormal_cross_country_coordinates_do_not_build_navigation_route(): void
    {
        [$courier, $order] = $this->createCourierWithActiveOrder(Order::STATUS_ACCEPTED, [
            'last_lat' => 48.4647,
            'last_lng' => 35.0462,
            'lat' => 51.5074,
            'lng' => -0.1278,
        ]);

        $this->actingAs($courier, 'web');

        Livewire::test(MyOrders::class)
            ->call('navigate', $order->id)
            ->assertNotDispatched('build-route')
            ->assertDispatched('map:ui-error', function (array $payload): bool {
                $detail = $payload[0] ?? $payload;

                return ($detail['message'] ?? null) === 'Локація курʼєра не підтверджена';
            })
            ->assertDispatched('notify', type: 'error', message: 'Локація курʼєра не підтверджена');
    }


    public function test_confirmed_tracker_coords_are_persisted_and_used_for_navigation(): void
    {
        [$courier, $order] = $this->createCourierWithActiveOrder(Order::STATUS_ACCEPTED, [
            'last_lat' => 40.7128,
            'last_lng' => -74.0060,
            'lat' => 48.4670,
            'lng' => 35.0500,
        ]);

        $this->actingAs($courier, 'web');

        Livewire::test(LocationTracker::class)
            ->call('updateLocation', 48.4647, 35.0462, 15);

        $courier->refresh();

        $this->assertSame(48.4647, (float) $courier->last_lat);
        $this->assertSame(35.0462, (float) $courier->last_lng);

        Livewire::test(MyOrders::class)
            ->call('navigate', $order->id)
            ->assertDispatched('build-route', function (array $payload): bool {
                $detail = $payload[0] ?? $payload;

                return ($detail['fromLat'] ?? null) === 48.4647
                    && ($detail['fromLng'] ?? null) === 35.0462
                    && ($detail['toLat'] ?? null) === 48.467
                    && ($detail['toLng'] ?? null) === 35.05;
            });
    }

    public function test_my_orders_hides_stale_distance_chip_after_confirmed_local_tracker_location(): void
    {
        [$courier] = $this->createCourierWithActiveOrder(Order::STATUS_ACCEPTED, [
            'last_lat' => 40.7128,
            'last_lng' => -74.0060,
            'lat' => 48.4670,
            'lng' => 35.0500,
        ]);

        $this->actingAs($courier, 'web');

        Livewire::test(LocationTracker::class)
            ->call('updateLocation', 48.4647, 35.0462, 10);

        Livewire::test(MyOrders::class)
            ->assertDontSee('12631.7 км', false);
    }

    public function test_navigation_uses_only_confirmed_courier_coords(): void
    {
        [$courier, $order] = $this->createCourierWithActiveOrder(Order::STATUS_ACCEPTED, [
            'last_lat' => 48.4647,
            'last_lng' => 35.0462,
            'lat' => 48.4670,
            'lng' => 35.0500,
        ]);

        $this->actingAs($courier, 'web');

        Livewire::test(MyOrders::class)
            ->call('navigate', $order->id)
            ->assertDispatched('build-route', function (array $payload): bool {
                $detail = $payload[0] ?? $payload;

                return ($detail['fromLat'] ?? null) === 48.4647
                    && ($detail['fromLng'] ?? null) === 35.0462
                    && ($detail['toLat'] ?? null) === 48.467
                    && ($detail['toLng'] ?? null) === 35.05;
            });
    }

    private function extractMapBootstrapFromHtml(string $html): array
    {
        preg_match("/data-map-bootstrap='([^']*)'/", $html, $matches);

        $this->assertNotEmpty($matches, 'Expected data-map-bootstrap attribute in rendered HTML.');

        $decoded = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

        $this->assertIsArray($decoded, 'Expected map bootstrap JSON payload.');

        return $decoded;
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

    private function createCourierWithActiveOrder(string $orderStatus, array $coords = []): array
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $courier = $this->createCourier();
        $courier->goOnline();

        if (array_key_exists('last_lat', $coords) || array_key_exists('last_lng', $coords)) {
            $courier->update([
                'last_lat' => $coords['last_lat'] ?? $courier->last_lat,
                'last_lng' => $coords['last_lng'] ?? $courier->last_lng,
            ]);
        }

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => $orderStatus,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'вул. Навігаційна, 7',
            'price' => 125,
            'accepted_at' => now(),
            'started_at' => $orderStatus === Order::STATUS_IN_PROGRESS ? now() : null,
            'lat' => $coords['lat'] ?? 48.4647,
            'lng' => $coords['lng'] ?? 35.0462,
        ]);

        $courier->refresh();

        return [$courier, $order];
    }
}
