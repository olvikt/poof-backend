<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Client\OrderCreate;
use App\Models\ClientAddress;
use App\Models\Order;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class OrderCreateAddressInvariantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_precision_blocks_field_geocode_from_moving_coordinates(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Http::fake([
            'https://maps.googleapis.com/maps/api/geocode/json*' => Http::response([
                'status' => 'OK',
                'results' => [[
                    'geometry' => [
                        'location' => ['lat' => 49.11, 'lng' => 24.22],
                    ],
                ]],
            ]),
        ]);

        $component = Livewire::test(OrderCreate::class)
            ->set('street', 'Main Street')
            ->set('house', '7A')
            ->set('city', 'Kyiv')
            ->set('lat', 50.45)
            ->set('lng', 30.52)
            ->set('address_precision', 'exact');

        $instance = $component->instance();

        Closure::bind(function (): void {
            $this->geocodeFromFields();
        }, $instance, $instance)();

        $component
            ->assertSet('lat', 50.45)
            ->assertSet('lng', 30.52)
            ->assertSet('address_precision', 'exact');

        Http::assertNothingSent();
    }

    public function test_set_location_marks_point_as_exact_and_keeps_reverse_geocode_flow(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Http::fake([
            'https://maps.googleapis.com/maps/api/geocode/json*' => Http::response([
                'status' => 'OK',
                'results' => [[
                    'address_components' => [
                        ['long_name' => 'Main Street', 'types' => ['route']],
                        ['long_name' => '7A', 'types' => ['street_number']],
                        ['long_name' => 'Kyiv', 'types' => ['locality']],
                    ],
                ]],
            ]),
        ]);

        Livewire::test(OrderCreate::class)
            ->call('setLocation', 50.45, 30.52)
            ->assertSet('lat', 50.45)
            ->assertSet('lng', 30.52)
            ->assertSet('address_precision', 'exact')
            ->assertSet('street', 'Main Street')
            ->assertSet('house', '7A')
            ->assertSet('city', 'Kyiv')
            ->assertSet('address_text', 'Main Street 7A');
    }

    public function test_load_address_from_book_and_repeat_hydration_keep_exact_precision_semantics(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $address = ClientAddress::create([
            'user_id' => $user->id,
            'label' => 'home',
            'building_type' => 'apartment',
            'address_text' => 'Main Street 7A, Kyiv',
            'city' => 'Kyiv',
            'street' => 'Main Street',
            'house' => '7A',
            'lat' => 50.45,
            'lng' => 30.52,
        ]);

        $order = Order::createForTesting([
            'client_id' => $user->id,
            'order_type' => Order::TYPE_ONE_TIME,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'address_id' => null,
            'address_text' => 'Old Street 9, Kyiv',
            'lat' => 50.46,
            'lng' => 30.53,
            'scheduled_date' => now()->toDateString(),
            'scheduled_time_from' => '10:00',
            'scheduled_time_to' => '12:00',
            'handover_type' => Order::HANDOVER_DOOR,
            'bags_count' => 1,
            'price' => 100,
        ]);

        Http::fake([
            'https://maps.googleapis.com/maps/api/geocode/json*' => Http::response([
                'status' => 'OK',
                'results' => [[
                    'formatted_address' => 'Old Street 9, Kyiv',
                    'address_components' => [
                        ['long_name' => 'Old Street', 'types' => ['route']],
                        ['long_name' => '9', 'types' => ['street_number']],
                        ['long_name' => 'Kyiv', 'types' => ['locality']],
                    ],
                ]],
            ]),
        ]);

        Livewire::withQueryParams(['address_id' => $address->id])
            ->test(OrderCreate::class)
            ->assertSet('address_precision', 'exact')
            ->assertSet('coordsFromAddressBook', true)
            ->assertSet('lat', 50.45)
            ->assertSet('lng', 30.52);

        Livewire::withQueryParams(['repeat' => $order->id])
            ->test(OrderCreate::class)
            ->assertSet('address_precision', 'approx')
            ->assertSet('coordsFromAddressBook', true)
            ->assertSet('lat', 50.46)
            ->assertSet('lng', 30.53);
    }

    public function test_validate_coordinates_or_fail_keeps_missing_and_approx_errors(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Livewire::test(OrderCreate::class);
        $instance = $component->instance();

        Closure::bind(function (): void {
            $this->validateCoordinatesOrFail();
        }, $instance, $instance)();

        $component->assertHasErrors(['address_text']);

        $component
            ->set('lat', 50.45)
            ->set('lng', 30.52)
            ->set('address_precision', 'approx')
            ->set('coordsFromAddressBook', false);

        Closure::bind(function (): void {
            $this->validateCoordinatesOrFail();
        }, $instance, $instance)();

        $component->assertHasErrors(['address_text']);

        $component->set('coordsFromAddressBook', true);

        Closure::bind(function (): void {
            $this->validateCoordinatesOrFail();
        }, $instance, $instance)();

        $component->assertHasNoErrors(['address_text']);
    }
}
