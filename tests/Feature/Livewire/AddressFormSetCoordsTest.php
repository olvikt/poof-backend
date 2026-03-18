<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Client\AddressForm;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AddressFormSetCoordsTest extends TestCase
{
    public function test_it_updates_address_fields_from_map_reverse_geocode(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'address' => [
                    'road' => '12, Main Street',
                    'house_number' => '7A',
                    'city' => 'Kyiv',
                    'state' => 'Kyiv region',
                ],
            ]),
        ]);

        Livewire::test(AddressForm::class)
            ->call('setCoords', 50.45, 30.52, 'map')
            ->assertSet('street', 'Main Street')
            ->assertSet('house', '7A')
            ->assertSet('city', 'Kyiv')
            ->assertSet('region', 'Kyiv region')
            ->assertSet('search', 'Main Street 7A, Kyiv, Kyiv region');
    }

    public function test_it_uses_display_name_house_fallback_when_house_number_is_missing(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'display_name' => 'Ukraine, Kyiv, Main Street, 15B, district',
                'address' => [
                    'street' => 'Main Street',
                    'city' => 'Kyiv',
                    'state' => 'Kyiv region',
                ],
            ]),
        ]);

        Livewire::test(AddressForm::class)
            ->call('setCoords', 50.45, 30.52, 'map')
            ->assertSet('house', '15B')
            ->assertSet('search', 'Main Street 15B, Kyiv, Kyiv region');
    }

    public function test_it_keeps_manual_house_when_user_has_already_touched_it(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'address' => [
                    'road' => 'Main Street',
                    'house_number' => '99',
                    'city' => 'Kyiv',
                    'state' => 'Kyiv region',
                ],
            ]),
            'http://localhost/api/geocode*' => Http::response([], 500),
        ]);

        Livewire::test(AddressForm::class)
            ->set('street', 'Manual Street')
            ->set('city', 'Kyiv')
            ->set('house', '11A')
            ->call('updatedHouse')
            ->call('setCoords', 50.45, 30.52, 'map')
            ->assertSet('house', '11A')
            ->assertSet('street', 'Main Street')
            ->assertSet('search', 'Main Street 99, Kyiv, Kyiv region');
    }

    public function test_non_map_sources_only_update_coordinates_without_reverse_fill(): void
    {
        Http::fake();

        Livewire::test(AddressForm::class)
            ->set('street', 'Existing Street')
            ->set('city', 'Existing City')
            ->set('house', '5')
            ->call('setCoords', 50.45, 30.52, 'autocomplete')
            ->assertSet('lat', 50.45)
            ->assertSet('lng', 30.52)
            ->assertSet('street', 'Existing Street')
            ->assertSet('city', 'Existing City')
            ->assertSet('house', '5');

        Http::assertNothingSent();
    }

    public function test_it_does_not_break_existing_state_on_unsuccessful_reverse_geocode(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([], 500),
        ]);

        Livewire::test(AddressForm::class)
            ->set('street', 'Existing Street')
            ->set('city', 'Existing City')
            ->set('region', 'Existing Region')
            ->set('house', '5')
            ->set('search', 'Existing Street 5, Existing City')
            ->call('setCoords', 50.45, 30.52, 'map')
            ->assertSet('street', 'Existing Street')
            ->assertSet('city', 'Existing City')
            ->assertSet('region', 'Existing Region')
            ->assertSet('house', '5')
            ->assertSet('search', 'Existing Street 5, Existing City');
    }

    public function test_reverse_geocode_exceptions_do_not_corrupt_existing_state(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => fn () => throw new \RuntimeException('boom'),
        ]);

        Livewire::test(AddressForm::class)
            ->set('street', 'Existing Street')
            ->set('city', 'Existing City')
            ->set('house', '5')
            ->set('search', 'Existing Street 5, Existing City')
            ->call('setCoords', 50.45, 30.52, 'map')
            ->assertSet('street', 'Existing Street')
            ->assertSet('city', 'Existing City')
            ->assertSet('house', '5')
            ->assertSet('search', 'Existing Street 5, Existing City');
    }
}
