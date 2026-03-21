<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Client\AddressForm;
use Closure;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AddressFormUpdatedHouseTest extends TestCase
{
    public function test_manual_house_update_sets_coordinates_and_dispatches_marker(): void
    {
        Http::fake([
            'http://localhost/api/geocode*' => Http::response([
                ['lat' => '50.4501', 'lng' => '30.5234'],
            ]),
        ]);

        Livewire::test(AddressForm::class)
            ->set('street', 'Main Street')
            ->set('city', 'Kyiv')
            ->set('house', '7A')
            ->assertSet('lat', 50.4501)
            ->assertSet('lng', 30.5234)
            ->assertDispatched('map:set-marker', lat: 50.4501, lng: 30.5234);
    }

    public function test_it_geocodes_house_update_using_search_fallback_when_street_is_empty(): void
    {
        Http::fake([
            'http://localhost/api/geocode*' => Http::response([
                ['lat' => '49.8397', 'lng' => '24.0297'],
            ]),
        ]);

        Livewire::test(AddressForm::class)
            ->set('search', 'Shevchenka Street, Lviv')
            ->set('house', '15B')
            ->assertSet('lat', 49.8397)
            ->assertSet('lng', 24.0297);

        Http::assertSent(function ($request) {
            return $request['q'] === 'Shevchenka Street, 15B, Lviv';
        });
    }

    public function test_programmatic_house_update_guard_skips_forward_geocode(): void
    {
        Http::fake();

        $component = Livewire::test(AddressForm::class)
            ->set('street', 'Main Street')
            ->set('city', 'Kyiv');

        $instance = $component->instance();

        Closure::bind(function () {
            $this->updatingHouseFromMap = true;
        }, $instance, $instance)();

        $component
            ->set('house', '10')
            ->assertSet('lat', null)
            ->assertSet('lng', null);

        Http::assertNothingSent();
    }


    public function test_exact_manual_point_is_not_overwritten_by_forward_geocode(): void
    {
        Http::fake([
            'http://localhost/api/geocode*' => Http::response([
                ['lat' => '49.8397', 'lng' => '24.0297'],
            ]),
        ]);

        Livewire::test(AddressForm::class)
            ->call('setCoords', 50.45, 30.52, 'map')
            ->set('street', 'Main Street')
            ->set('city', 'Kyiv')
            ->set('house', '7A')
            ->assertSet('lat', 50.45)
            ->assertSet('lng', 30.52)
            ->assertSet('addressPrecision', 'exact');
    }

    public function test_unsuccessful_geocode_response_does_not_break_existing_state(): void
    {
        Http::fake([
            'http://localhost/api/geocode*' => Http::response([], 500),
        ]);

        Livewire::test(AddressForm::class)
            ->set('street', 'Main Street')
            ->set('city', 'Kyiv')
            ->set('lat', 50.45)
            ->set('lng', 30.52)
            ->set('house', '7A')
            ->assertSet('lat', 50.45)
            ->assertSet('lng', 30.52);
    }

    public function test_missing_coordinates_in_geocode_response_do_not_break_existing_state(): void
    {
        Http::fake([
            'http://localhost/api/geocode*' => Http::response([
                ['lat' => '50.45'],
            ]),
        ]);

        Livewire::test(AddressForm::class)
            ->set('street', 'Main Street')
            ->set('city', 'Kyiv')
            ->set('lat', 50.45)
            ->set('lng', 30.52)
            ->set('house', '7A')
            ->assertSet('lat', 50.45)
            ->assertSet('lng', 30.52);
    }
}
