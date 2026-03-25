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

        $crawler = new \Symfony\Component\DomCrawler\Crawler($html);

        $ordersLink = $this->findCourierTabLink($crawler, route('courier.orders'));
        $myOrdersLink = $this->findCourierTabLink($crawler, route('courier.my-orders'));

        $this->assertNotNull($ordersLink, 'Courier orders tab link should exist in layout.');
        $this->assertNotNull($myOrdersLink, 'Courier my-orders tab link should exist in layout.');

        $this->assertTrue($this->hasWireNavigateAttribute($ordersLink), 'Orders tab should expose Livewire SPA navigation.');
        $this->assertTrue($this->hasWireNavigateAttribute($myOrdersLink), 'My-orders tab should expose Livewire SPA navigation.');
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
            ->assertDispatched('map:ui-error', function (...$payload): bool {
                $detail = $this->extractDispatchedEventDetail($payload);

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

        $this->assertEqualsWithDelta(48.4647, (float) $courier->last_lat, 0.000001);
        $this->assertEqualsWithDelta(35.0462, (float) $courier->last_lng, 0.000001);

        Livewire::test(MyOrders::class)
            ->call('navigate', $order->id)
            ->assertDispatched('build-route', function (...$payload): bool {
                $detail = $this->extractDispatchedEventDetail($payload);

                return $this->payloadHasRouteCoords(
                    $detail,
                    fromLat: 48.4647,
                    fromLng: 35.0462,
                    toLat: 48.467,
                    toLng: 35.05,
                );
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
            ->assertDispatched('build-route', function (...$payload): bool {
                $detail = $this->extractDispatchedEventDetail($payload);

                return $this->payloadHasRouteCoords(
                    $detail,
                    fromLat: 48.4647,
                    fromLng: 35.0462,
                    toLat: 48.467,
                    toLng: 35.05,
                );
            });
    }


    private function findCourierTabLink(\Symfony\Component\DomCrawler\Crawler $crawler, string $targetUrl): ?\DOMElement
    {
        $targetPath = $this->normalizePath((string) parse_url($targetUrl, PHP_URL_PATH));

        foreach ($crawler->filter('a[href]') as $link) {
            if (! $link instanceof \DOMElement) {
                continue;
            }

            $href = $link->getAttribute('href');
            $hrefPath = $this->normalizePath((string) (parse_url($href, PHP_URL_PATH) ?: $href));

            if ($hrefPath !== $targetPath) {
                continue;
            }

            return $link;
        }

        return null;
    }

    private function normalizePath(string $path): string
    {
        $normalized = '/' . ltrim($path, '/');

        if ($normalized !== '/') {
            $normalized = rtrim($normalized, '/');
        }

        return $normalized;
    }

    private function extractDispatchedEventDetail(array $payload): array
    {
        $targetKeys = ['fromLat', 'fromLng', 'toLat', 'toLng', 'message'];

        $stack = [$payload];

        while ($stack !== []) {
            $candidate = array_pop($stack);

            if (! is_array($candidate)) {
                continue;
            }

            $candidateKeys = array_keys($candidate);

            if (count(array_intersect($targetKeys, $candidateKeys)) > 0) {
                return $candidate;
            }

            foreach ($candidate as $value) {
                if (is_array($value)) {
                    $stack[] = $value;
                }
            }
        }

        return [];
    }

    private function hasWireNavigateAttribute(\DOMElement $link): bool
    {
        if ($link->hasAttribute('wire:navigate')) {
            return true;
        }

        if (! $link->hasAttributes()) {
            return false;
        }

        foreach ($link->attributes as $attribute) {
            if (! $attribute instanceof \DOMAttr) {
                continue;
            }

            if (str_starts_with($attribute->name, 'wire:navigate')) {
                return true;
            }

            if (($attribute->prefix ?? null) === 'wire' && str_starts_with($attribute->localName, 'navigate')) {
                return true;
            }
        }

        return false;
    }

    private function payloadHasRouteCoords(
        array $detail,
        float $fromLat,
        float $fromLng,
        float $toLat,
        float $toLng,
        float $delta = 0.000001,
    ): bool {
        return $this->floatMatches($detail['fromLat'] ?? null, $fromLat, $delta)
            && $this->floatMatches($detail['fromLng'] ?? null, $fromLng, $delta)
            && $this->floatMatches($detail['toLat'] ?? null, $toLat, $delta)
            && $this->floatMatches($detail['toLng'] ?? null, $toLng, $delta);
    }

    private function floatMatches(mixed $actual, float $expected, float $delta): bool
    {
        if (! is_numeric($actual)) {
            return false;
        }

        return abs(((float) $actual) - $expected) <= $delta;
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
