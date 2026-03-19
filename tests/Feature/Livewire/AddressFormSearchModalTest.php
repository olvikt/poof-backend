<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Client\AddressForm;
use App\Models\ClientAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AddressFormSearchModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_ui_uses_single_address_entrypoint_without_visible_city_or_region_fields(): void
    {
        Livewire::test(AddressForm::class)
            ->assertSee('Пошук адреси')
            ->assertSee('Введіть адресу, будинок або виберіть точку на мапі')
            ->assertDontSee('Місто')
            ->assertDontSee('Область')
            ->assertDontSee('Буд.');
    }

    public function test_it_opens_address_search_modal_from_primary_entrypoint(): void
    {
        Livewire::test(AddressForm::class)
            ->assertSet('isAddressSearchOpen', false)
            ->call('openAddressSearch')
            ->assertSet('isAddressSearchOpen', true)
            ->assertSee('Оберіть адресу')
            ->assertSee('Нещодавні адреси')
            ->assertSee('Очистити');
    }

    public function test_selecting_suggestion_closes_modal_updates_internal_state_and_keeps_map_sync(): void
    {
        Livewire::test(AddressForm::class)
            ->call('openAddressSearch')
            ->set('suggestions', [[
                'label' => 'Main Street 7A, Kyiv',
                'line1' => 'Main Street 7A',
                'line2' => 'Kyiv, Kyiv region',
                'street' => 'Main Street',
                'house' => '7A',
                'city' => 'Kyiv',
                'region' => 'Kyiv region',
                'lat' => 50.45,
                'lng' => 30.52,
            ]])
            ->call('selectSuggestion', 0)
            ->assertSet('isAddressSearchOpen', false)
            ->assertSet('search', 'Main Street 7A, Kyiv')
            ->assertSet('street', 'Main Street')
            ->assertSet('house', '7A')
            ->assertSet('city', 'Kyiv')
            ->assertSet('region', 'Kyiv region')
            ->assertSet('lat', 50.45)
            ->assertSet('lng', 30.52)
            ->assertDispatched('map:set-marker', lat: 50.45, lng: 30.52)
            ->assertDispatched('map:set-location', lat: 50.45, lng: 30.52, source: 'autocomplete', zoom: 17)
            ->assertDispatched('map:update', lat: 50.45, lng: 30.52, zoom: 17);
    }

    public function test_save_flow_still_works_after_primary_inputs_are_removed_from_ui(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(AddressForm::class)
            ->set('label', 'home')
            ->set('building_type', 'apartment')
            ->call('openAddressSearch')
            ->set('suggestions', [[
                'label' => 'Main Street 7A, Kyiv',
                'line1' => 'Main Street 7A',
                'line2' => 'Kyiv, Kyiv region',
                'street' => 'Main Street',
                'house' => '7A',
                'city' => 'Kyiv',
                'region' => 'Kyiv region',
                'lat' => 50.45,
                'lng' => 30.52,
            ]])
            ->call('selectSuggestion', 0)
            ->set('entrance', '1')
            ->set('floor', '3')
            ->set('apartment', '15')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('address-saved');

        $this->assertDatabaseHas('client_addresses', [
            'user_id' => $user->id,
            'address_text' => 'Main Street 7A, Kyiv',
            'street' => 'Main Street',
            'house' => '7A',
            'city' => 'Kyiv',
            'region' => 'Kyiv region',
            'lat' => 50.45,
            'lng' => 30.52,
        ]);
    }

    public function test_recent_address_payload_fields_stay_available_when_saved_address_is_reopened(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(AddressForm::class)
            ->set('label', 'home')
            ->set('building_type', 'apartment')
            ->call('openAddressSearch')
            ->set('suggestions', [[
                'label' => 'Мандриківська 173, Дніпро',
                'line1' => 'Мандриківська 173',
                'line2' => 'Дніпро, Дніпропетровська область',
                'street' => 'Мандриківська',
                'house' => '173',
                'city' => 'Дніпро',
                'region' => 'Дніпропетровська область',
                'lat' => 48.4647,
                'lng' => 35.0462,
            ]])
            ->call('selectSuggestion', 0)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('address-saved');

        $addressId = ClientAddress::query()
            ->where('user_id', $user->id)
            ->value('id');

        Livewire::test(AddressForm::class)
            ->call('open', $addressId)
            ->assertSet('search', 'Мандриківська 173, Дніпро')
            ->assertSet('street', 'Мандриківська')
            ->assertSet('house', '173')
            ->assertSet('city', 'Дніпро')
            ->assertSet('region', 'Дніпропетровська область')
            ->assertSet('lat', 48.4647)
            ->assertSet('lng', 35.0462);
    }

    public function test_modal_header_is_clean_and_apartment_inputs_use_floating_numeric_friendly_markup(): void
    {
        Livewire::test(AddressForm::class)
            ->call('openAddressSearch')
            ->assertSee('Оберіть адресу')
            ->assertDontSee('Bolt / Uber style fullscreen пошук з автодоповненням')
            ->assertSeeHtml('inputmode="numeric"')
            ->assertSeeHtml('pattern="[0-9]*"')
            ->assertSee('Підʼїзд')
            ->assertSee('Поверх')
            ->assertSee('Кв./офіс')
            ->assertSee('Домофон');
    }

}
