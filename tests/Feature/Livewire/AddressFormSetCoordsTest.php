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

    public function test_it_ignores_unknown_sync_sources_before_mutating_current_visible_point(): void
    {
        $this->mock(ResolveAddressFromPoint::class)
            ->shouldNotReceive('execute');

        Livewire::test(AddressForm::class)
            ->set('search', 'Мандриківська 173, Dnipro')
            ->set('summarySearch', 'Мандриківська 173, Dnipro')
            ->set('street', 'Мандриківська')
            ->set('house', '173')
            ->set('city', 'Dnipro')
            ->set('region', 'Dnipropetrovsk region')
            ->set('lat', 48.4671)
            ->set('lng', 35.0382)
            ->set('addressPrecision', 'exact')
            ->set('selectedAddressLocked', true)
            ->set('suggestions', [[
                'label' => 'Старий автокомпліт',
                'lat' => 48.4671,
                'lng' => 35.0382,
            ]])
            ->call('setCoords', 48.4240053, 35.0588747, 'sync')
            ->assertSet('search', 'Мандриківська 173, Dnipro')
            ->assertSet('summarySearch', 'Мандриківська 173, Dnipro')
            ->assertSet('street', 'Мандриківська')
            ->assertSet('house', '173')
            ->assertSet('lat', 48.4671)
            ->assertSet('lng', 35.0382)
            ->assertSet('addressPrecision', 'exact')
            ->assertSet('selectedAddressLocked', true)
            ->assertSet('suggestions', [[
                'label' => 'Старий автокомпліт',
                'lat' => 48.4671,
                'lng' => 35.0382,
            ]]);
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


    public function test_select_suggestion_resets_manual_house_guard_for_new_address_context(): void
    {
        Http::fake([
            url('/api/geocode').'*' => Http::response([], 500),
        ]);

        $this->mock(ResolveAddressFromPoint::class)
            ->shouldReceive('execute')
            ->once()
            ->andReturn(new ResolvedAddressData(
                street: 'Suggestion Street',
                house: '42',
                city: 'Kyiv',
                region: 'Kyiv region',
                search: 'Suggestion Street 42, Kyiv, Kyiv region',
            ));

        Livewire::test(AddressForm::class)
            ->set('street', 'Manual Street')
            ->set('city', 'Kyiv')
            ->set('house', '11A')
            ->call('updatedHouse')
            ->call('setPhotonSuggestions', [[
                'lat' => 50.46,
                'lng' => 30.53,
                'street' => 'Suggestion Street',
                'house' => null,
                'city' => 'Kyiv',
                'region' => 'Kyiv region',
                'label' => 'Suggestion Street, Kyiv',
            ]])
            ->call('selectSuggestion', 0)
            ->assertSet('houseTouchedManually', false)
            ->call('setCoords', 50.46, 30.53, 'map')
            ->assertSet('house', '42')
            ->assertSet('street', 'Suggestion Street')
            ->assertSet('search', 'Suggestion Street 42, Kyiv, Kyiv region');
    }

    public function test_clear_search_resets_manual_house_guard_for_new_address_context(): void
    {
        Http::fake([
            url('/api/geocode').'*' => Http::response([], 500),
        ]);

        $this->mock(ResolveAddressFromPoint::class)
            ->shouldReceive('execute')
            ->once()
            ->andReturn(new ResolvedAddressData(
                street: 'New Street',
                house: '21',
                city: 'Dnipro',
                region: 'Dnipropetrovsk region',
                search: 'New Street 21, Dnipro, Dnipropetrovsk region',
            ));

        Livewire::test(AddressForm::class)
            ->set('street', 'Manual Street')
            ->set('city', 'Kyiv')
            ->set('house', '11A')
            ->call('updatedHouse')
            ->call('clearSearch')
            ->assertSet('houseTouchedManually', false)
            ->call('setCoords', 48.46, 35.05, 'map')
            ->assertSet('house', '21')
            ->assertSet('street', 'New Street')
            ->assertSet('search', 'New Street 21, Dnipro, Dnipropetrovsk region');
    }


    public function test_stale_late_geolocation_update_does_not_override_selected_suggestion(): void
    {
        $this->mock(ResolveAddressFromPoint::class)
            ->shouldNotReceive('execute');

        Livewire::test(AddressForm::class)
            ->call('setPhotonSuggestions', [[
                'lat' => 48.4671,
                'lng' => 35.0382,
                'street' => 'Мандриківська',
                'house' => '173',
                'city' => 'Dnipro',
                'region' => 'Dnipropetrovsk region',
                'label' => 'Мандриківська 173, Dnipro',
            ]])
            ->call('selectSuggestion', 0)
            ->call('setCoords', 48.5001, 35.1002, 'geolocation')
            ->assertSet('lat', 48.4671)
            ->assertSet('lng', 35.0382)
            ->assertSet('street', 'Мандриківська')
            ->assertSet('house', '173')
            ->assertSet('search', 'Мандриківська 173, Dnipro')
            ->assertSet('summarySearch', 'Мандриківська 173, Dnipro');
    }

    public function test_manual_map_correction_after_my_location_updates_visible_search_summary(): void
    {
        $this->mock(ResolveAddressFromPoint::class)
            ->shouldReceive('execute')
            ->once()
            ->andReturn(new ResolvedAddressData(
                street: 'Мандриківська',
                house: '173',
                city: 'Dnipro',
                region: 'Dnipropetrovsk region',
                search: 'Мандриківська 173, Dnipro, Dnipropetrovsk region',
            ));

        Livewire::test(AddressForm::class)
            ->set('search', 'Моя локація')
            ->set('summarySearch', 'Моя локація')
            ->call('setCoords', 48.4671, 35.0382, 'user')
            ->assertSet('lat', 48.4671)
            ->assertSet('lng', 35.0382)
            ->assertSet('street', 'Мандриківська')
            ->assertSet('house', '173')
            ->assertSet('search', 'Мандриківська 173, Dnipro, Dnipropetrovsk region')
            ->assertSet('summarySearch', 'Мандриківська 173, Dnipro, Dnipropetrovsk region');
    }

    public function test_manual_marker_move_updates_visible_address_summary_to_latest_resolved_point(): void
    {
        $this->mock(ResolveAddressFromPoint::class)
            ->shouldReceive('execute')
            ->twice()
            ->andReturn(
                new ResolvedAddressData(
                    street: 'Мандриківська',
                    house: '173',
                    city: 'Dnipro',
                    region: 'Dnipropetrovsk region',
                    search: 'Мандриківська 173, Dnipro, Dnipropetrovsk region',
                ),
                new ResolvedAddressData(
                    street: 'Мандриківська',
                    house: '171',
                    city: 'Dnipro',
                    region: 'Dnipropetrovsk region',
                    search: 'Мандриківська 171, Dnipro, Dnipropetrovsk region',
                ),
            );

        Livewire::test(AddressForm::class)
            ->call('setCoords', 48.4671, 35.0382, 'map')
            ->assertSet('search', 'Мандриківська 173, Dnipro, Dnipropetrovsk region')
            ->assertSet('summarySearch', 'Мандриківська 173, Dnipro, Dnipropetrovsk region')
            ->call('setCoords', 48.4669, 35.0379, 'map')
            ->assertSet('lat', 48.4669)
            ->assertSet('lng', 35.0379)
            ->assertSet('street', 'Мандриківська')
            ->assertSet('house', '171')
            ->assertSet('search', 'Мандриківська 171, Dnipro, Dnipropetrovsk region')
            ->assertSet('summarySearch', 'Мандриківська 171, Dnipro, Dnipropetrovsk region')
            ->assertSet('addressPrecision', 'exact');
    }

    public function test_late_geolocation_does_not_override_manual_map_point_that_has_settled(): void
    {
        $this->mock(ResolveAddressFromPoint::class)
            ->shouldReceive('execute')
            ->once()
            ->andReturn(new ResolvedAddressData(
                street: 'Мандриківська',
                house: '171',
                city: 'Dnipro',
                region: 'Dnipropetrovsk region',
                search: 'Мандриківська 171, Dnipro, Dnipropetrovsk region',
            ));

        Livewire::test(AddressForm::class)
            ->call('setCoords', 48.4669, 35.0379, 'map')
            ->assertSet('addressPrecision', 'exact')
            ->call('setCoords', 48.5001, 35.1002, 'geolocation')
            ->assertSet('lat', 48.4669)
            ->assertSet('lng', 35.0379)
            ->assertSet('street', 'Мандриківська')
            ->assertSet('house', '171')
            ->assertSet('search', 'Мандриківська 171, Dnipro, Dnipropetrovsk region')
            ->assertSet('summarySearch', 'Мандриківська 171, Dnipro, Dnipropetrovsk region');
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
