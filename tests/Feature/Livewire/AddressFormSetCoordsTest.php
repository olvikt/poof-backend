<?php

namespace Tests\Feature\Livewire;

use App\DTO\Address\ResolvedAddressData;
use App\Livewire\Client\AddressForm;
use App\Services\Address\ResolveAddressFromPoint;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AddressFormSetCoordsTest extends TestCase
{
    public function test_it_applies_reverse_geocode_result_for_map_source(): void
    {
        $this->mock(ResolveAddressFromPoint::class)
            ->shouldReceive('execute')
            ->once()
            ->andReturn(new ResolvedAddressData(
                street: 'Main Street',
                house: '7A',
                city: 'Kyiv',
                region: 'Kyiv region',
                search: 'Main Street 7A, Kyiv, Kyiv region',
            ));

        Livewire::test(AddressForm::class)
            ->call('setCoords', 50.45, 30.52, 'map')
            ->assertSet('lat', 50.45)
            ->assertSet('lng', 30.52)
            ->assertSet('street', 'Main Street')
            ->assertSet('house', '7A')
            ->assertSet('city', 'Kyiv')
            ->assertSet('region', 'Kyiv region')
            ->assertSet('search', 'Main Street 7A, Kyiv, Kyiv region')
            ->assertSet('addressPrecision', 'exact');
    }

    public function test_it_applies_house_fallbacks_resolved_by_reverse_geocode_service(): void
    {
        $this->mock(ResolveAddressFromPoint::class)
            ->shouldReceive('execute')
            ->once()
            ->andReturn(new ResolvedAddressData(
                street: 'Набережна Перемоги',
                house: '108 к5',
                city: 'Dnipro',
                region: 'Dnipropetrovsk region',
                search: 'Набережна Перемоги 108 к5, Dnipro, Dnipropetrovsk region',
            ));

        Livewire::test(AddressForm::class)
            ->call('setCoords', 50.45, 30.52, 'map')
            ->assertSet('house', '108 к5')
            ->assertSet('search', 'Набережна Перемоги 108 к5, Dnipro, Dnipropetrovsk region');
    }

    public function test_it_keeps_manual_house_when_user_has_already_touched_it(): void
    {
        Http::fake([
            url('/api/geocode').'*' => Http::response([], 500),
        ]);

        $this->mock(ResolveAddressFromPoint::class)
            ->shouldReceive('execute')
            ->once()
            ->andReturn(new ResolvedAddressData(
                street: 'Main Street',
                house: '99',
                city: 'Kyiv',
                region: 'Kyiv region',
                search: 'Main Street 99, Kyiv, Kyiv region',
            ));

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
        $this->mock(ResolveAddressFromPoint::class)
            ->shouldNotReceive('execute');

        Livewire::test(AddressForm::class)
            ->set('street', 'Existing Street')
            ->set('city', 'Existing City')
            ->set('house', '5')
            ->call('setCoords', 50.45, 30.52, 'autocomplete')
            ->assertSet('lat', 50.45)
            ->assertSet('lng', 30.52)
            ->assertSet('addressPrecision', 'approx')
            ->assertSet('street', 'Existing Street')
            ->assertSet('city', 'Existing City')
            ->assertSet('house', '5');
    }

    public function test_it_preserves_existing_text_fields_when_reverse_geocode_returns_no_result(): void
    {
        $this->mock(ResolveAddressFromPoint::class)
            ->shouldReceive('execute')
            ->once()
            ->andReturn(null);

        Livewire::test(AddressForm::class)
            ->set('street', 'Existing Street')
            ->set('city', 'Existing City')
            ->set('region', 'Existing Region')
            ->set('house', '5')
            ->set('search', 'Existing Street 5, Existing City')
            ->call('setCoords', 50.45, 30.52, 'map')
            ->assertSet('lat', 50.45)
            ->assertSet('lng', 30.52)
            ->assertSet('street', 'Existing Street')
            ->assertSet('city', 'Existing City')
            ->assertSet('region', 'Existing Region')
            ->assertSet('house', '5')
            ->assertSet('search', 'Existing Street 5, Existing City')
            ->assertSet('addressPrecision', 'exact');
    }

    public function test_service_level_reverse_geocode_failures_bubble_up_as_null_results_without_corrupting_state(): void
    {
        $this->mock(ResolveAddressFromPoint::class)
            ->shouldReceive('execute')
            ->once()
            ->andReturn(null);

        Livewire::test(AddressForm::class)
            ->set('street', 'Existing Street')
            ->set('city', 'Existing City')
            ->set('house', '5')
            ->set('search', 'Existing Street 5, Existing City')
            ->call('setCoords', 50.45, 30.52, 'map')
            ->assertSet('lat', 50.45)
            ->assertSet('lng', 30.52)
            ->assertSet('street', 'Existing Street')
            ->assertSet('city', 'Existing City')
            ->assertSet('house', '5')
            ->assertSet('search', 'Existing Street 5, Existing City')
            ->assertSet('addressPrecision', 'exact');
    }
}
