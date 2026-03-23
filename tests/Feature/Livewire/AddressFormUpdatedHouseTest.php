<?php

namespace Tests\Feature\Livewire;

use App\DTO\Address\AddressFieldsData;
use App\DTO\Address\ResolvedAddressPointData;
use App\Livewire\Client\AddressForm;
use App\Services\Address\ResolveAddressPointFromFields;
use Closure;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class AddressFormUpdatedHouseTest extends TestCase
{
    public function test_manual_house_update_sets_manual_guard_coordinates_precision_and_marker_from_resolution_service(): void
    {
        $this->mock(ResolveAddressPointFromFields::class)
            ->shouldReceive('execute')
            ->once()
            ->with(Mockery::on(function (AddressFieldsData $fields): bool {
                return $fields->street === 'Main Street'
                    && $fields->house === '7A'
                    && $fields->city === 'Kyiv'
                    && $fields->search === null
                    && $fields->lat === null
                    && $fields->lng === null;
            }))
            ->andReturn(new ResolvedAddressPointData(
                lat: 50.4501,
                lng: 30.5234,
                query: 'Main Street, 7A, Kyiv',
            ));

        Livewire::test(AddressForm::class)
            ->set('street', 'Main Street')
            ->set('city', 'Kyiv')
            ->set('house', '7A')
            ->assertSet('houseTouchedManually', true)
            ->assertSet('lat', 50.4501)
            ->assertSet('lng', 30.5234)
            ->assertSet('addressPrecision', 'approx')
            ->assertDispatched('map:set-marker', lat: 50.4501, lng: 30.5234)
            ->assertDispatched('map:set-marker-precision', precision: 'approx');
    }

    public function test_it_resolves_house_update_using_search_fallback_via_resolution_service_contract(): void
    {
        $this->mock(ResolveAddressPointFromFields::class)
            ->shouldReceive('execute')
            ->once()
            ->with(Mockery::on(function (AddressFieldsData $fields): bool {
                return $fields->street === null
                    && $fields->house === '15B'
                    && $fields->city === null
                    && $fields->search === 'Shevchenka Street, Lviv';
            }))
            ->andReturn(new ResolvedAddressPointData(
                lat: 49.8397,
                lng: 24.0297,
                query: 'Shevchenka Street, 15B, Lviv',
            ));

        Livewire::test(AddressForm::class)
            ->set('search', 'Shevchenka Street, Lviv')
            ->set('house', '15B')
            ->assertSet('houseTouchedManually', true)
            ->assertSet('lat', 49.8397)
            ->assertSet('lng', 24.0297)
            ->assertSet('addressPrecision', 'approx');
    }

    public function test_programmatic_house_update_guard_skips_forward_geocode_service(): void
    {
        $this->mock(ResolveAddressPointFromFields::class)
            ->shouldNotReceive('execute');

        $component = Livewire::test(AddressForm::class)
            ->set('street', 'Main Street')
            ->set('city', 'Kyiv');

        $instance = $component->instance();

        Closure::bind(function () {
            $this->updatingHouseFromMap = true;
        }, $instance, $instance)();

        $component
            ->set('house', '10')
            ->assertSet('houseTouchedManually', false)
            ->assertSet('lat', null)
            ->assertSet('lng', null)
            ->assertSet('addressPrecision', 'none');
    }

    public function test_exact_manual_point_is_not_overwritten_by_field_resolution_result(): void
    {
        $this->mock(ResolveAddressPointFromFields::class)
            ->shouldReceive('execute')
            ->once()
            ->andReturn(new ResolvedAddressPointData(
                lat: 49.8397,
                lng: 24.0297,
                query: 'Main Street, 7A, Kyiv',
            ));

        Livewire::test(AddressForm::class)
            ->call('setCoords', 50.45, 30.52, 'map')
            ->set('street', 'Main Street')
            ->set('city', 'Kyiv')
            ->set('house', '7A')
            ->assertSet('houseTouchedManually', true)
            ->assertSet('lat', 50.45)
            ->assertSet('lng', 30.52)
            ->assertSet('addressPrecision', 'exact');
    }

    public function test_null_resolution_preserves_existing_manual_state(): void
    {
        $this->mock(ResolveAddressPointFromFields::class)
            ->shouldReceive('execute')
            ->once()
            ->andReturn(null);

        Livewire::test(AddressForm::class)
            ->set('street', 'Main Street')
            ->set('city', 'Kyiv')
            ->set('lat', 50.45)
            ->set('lng', 30.52)
            ->set('addressPrecision', 'approx')
            ->set('house', '7A')
            ->assertSet('houseTouchedManually', true)
            ->assertSet('lat', 50.45)
            ->assertSet('lng', 30.52)
            ->assertSet('addressPrecision', 'approx');
    }

    public function test_partial_or_failed_resolution_result_does_not_overwrite_existing_exact_point(): void
    {
        $this->mock(ResolveAddressPointFromFields::class)
            ->shouldReceive('execute')
            ->once()
            ->andReturn(null);

        Livewire::test(AddressForm::class)
            ->set('street', 'Main Street')
            ->set('city', 'Kyiv')
            ->set('lat', 50.45)
            ->set('lng', 30.52)
            ->set('addressPrecision', 'exact')
            ->set('house', '7A')
            ->assertSet('houseTouchedManually', true)
            ->assertSet('lat', 50.45)
            ->assertSet('lng', 30.52)
            ->assertSet('addressPrecision', 'exact');
    }
}
