<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Client\AddressForm;
use App\Models\ClientAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AddressFormSaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_address_via_save_flow_and_keeps_dispatch_contracts(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(AddressForm::class)
            ->set('label', 'home')
            ->set('title', 'Дім')
            ->set('building_type', 'apartment')
            ->set('search', 'Main Street, Kyiv')
            ->set('city', 'Kyiv')
            ->set('region', 'Kyiv region')
            ->set('street', 'Main Street')
            ->set('house', '7A')
            ->set('lat', 50.45)
            ->set('lng', 30.52)
            ->set('entrance', '1')
            ->set('intercom', '22')
            ->set('floor', '3')
            ->set('apartment', '15')
            ->call('save')
            ->assertDispatched('address-saved')
            ->assertDispatchedTo('client.address-manager', 'address-saved')
            ->assertDispatched('sheet:close', name: 'addressForm')
            ->assertDispatched('sheet:close');

        $this->assertDatabaseHas('client_addresses', [
            'user_id' => $user->id,
            'label' => 'home',
            'title' => 'Дім',
            'building_type' => 'apartment',
            'address_text' => 'Main Street, Kyiv',
            'city' => 'Kyiv',
            'street' => 'Main Street',
            'house' => '7A',
            'entrance' => '1',
            'apartment' => '15',
            'geocode_source' => 'manual',
            'geocode_accuracy' => 'exact',
        ]);
    }

    public function test_it_persists_apartment_only_fields_only_for_apartment_addresses(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(AddressForm::class)
            ->set('label', 'home')
            ->set('title', 'Квартира')
            ->set('building_type', 'apartment')
            ->set('search', 'Apartment Street 1, Kyiv')
            ->set('city', 'Kyiv')
            ->set('street', 'Apartment Street')
            ->set('house', '1')
            ->set('lat', 50.45)
            ->set('lng', 30.52)
            ->set('entrance', '2')
            ->set('intercom', '45')
            ->set('floor', '8')
            ->set('apartment', '81')
            ->call('save')
            ->assertHasNoErrors();

        $apartmentAddress = ClientAddress::query()->where('user_id', $user->id)->sole();

        $this->assertSame('2', $apartmentAddress->entrance);
        $this->assertSame('45', $apartmentAddress->intercom);
        $this->assertSame('8', $apartmentAddress->floor);
        $this->assertSame('81', $apartmentAddress->apartment);

        Livewire::test(AddressForm::class)
            ->set('addressId', $apartmentAddress->id)
            ->set('label', 'work')
            ->set('title', 'Будинок')
            ->set('building_type', 'house')
            ->set('search', 'House Street 9, Kyiv')
            ->set('city', 'Kyiv')
            ->set('street', 'House Street')
            ->set('house', '9')
            ->set('lat', 50.46)
            ->set('lng', 30.53)
            ->set('entrance', '9')
            ->set('intercom', '99')
            ->set('floor', '9')
            ->set('apartment', '99')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('client_addresses', [
            'id' => $apartmentAddress->id,
            'building_type' => 'house',
            'entrance' => null,
            'intercom' => null,
            'floor' => null,
            'apartment' => null,
        ]);
    }

    public function test_it_opens_existing_address_for_edit_and_dispatches_sheet_and_marker_events(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $address = ClientAddress::create([
            'user_id' => $user->id,
            'label' => 'work',
            'title' => 'Office',
            'building_type' => 'house',
            'address_text' => 'Updated Street 9B, Kyiv',
            'city' => 'Kyiv',
            'region' => 'Kyiv region',
            'street' => 'Updated Street',
            'house' => '9B',
            'lat' => 50.46,
            'lng' => 30.53,
        ]);

        Livewire::test(AddressForm::class)
            ->call('open', $address->id)
            ->assertSet('addressId', $address->id)
            ->assertSet('label', 'work')
            ->assertSet('title', 'Office')
            ->assertSet('building_type', 'house')
            ->assertSet('search', 'Updated Street 9B, Kyiv')
            ->assertSet('summarySearch', 'Updated Street 9B, Kyiv')
            ->assertSet('street', 'Updated Street')
            ->assertSet('house', '9B')
            ->assertSet('lat', 50.46)
            ->assertSet('lng', 30.53)
            ->assertSet('selectedAddressLocked', true)
            ->assertDispatched('sheet:open', name: 'addressForm')
            ->assertDispatched('map:set-marker', lat: 50.46, lng: 30.53)
            ->assertDispatched('map:set-marker-precision', precision: 'exact');
    }

    public function test_it_resets_search_state_when_opening_a_new_address_form(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(AddressForm::class)
            ->set('search', 'Old address')
            ->set('summarySearch', 'Old summary')
            ->set('suggestions', [['label' => 'Old address', 'lat' => 50.45, 'lng' => 30.52]])
            ->set('activeSuggestionIndex', 0)
            ->set('suggestionsMessage', 'Hint')
            ->set('lat', 50.45)
            ->set('lng', 30.52)
            ->set('selectedAddressLocked', true)
            ->call('open')
            ->assertSet('addressId', null)
            ->assertSet('search', null)
            ->assertSet('summarySearch', null)
            ->assertSet('suggestions', [])
            ->assertSet('activeSuggestionIndex', -1)
            ->assertSet('suggestionsMessage', null)
            ->assertSet('lat', null)
            ->assertSet('lng', null)
            ->assertSet('selectedAddressLocked', false)
            ->assertDispatched('sheet:open', name: 'addressForm');
    }

    public function test_it_updates_only_the_authenticated_users_address_via_save_flow(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $address = ClientAddress::create([
            'user_id' => $user->id,
            'label' => 'home',
            'building_type' => 'apartment',
            'address_text' => 'Old Address',
            'city' => 'Kyiv',
            'street' => 'Old Street',
            'house' => '1',
            'lat' => 50.45,
            'lng' => 30.52,
        ]);

        $foreignAddress = ClientAddress::create([
            'user_id' => $otherUser->id,
            'label' => 'work',
            'building_type' => 'house',
            'address_text' => 'Foreign Address',
            'city' => 'Lviv',
            'street' => 'Foreign Street',
            'house' => '2',
            'lat' => 49.84,
            'lng' => 24.03,
        ]);

        $this->actingAs($user);

        Livewire::test(AddressForm::class)
            ->set('addressId', $address->id)
            ->set('label', 'work')
            ->set('title', 'Офіс')
            ->set('building_type', 'house')
            ->set('search', 'Updated Street, Kyiv')
            ->set('city', 'Kyiv')
            ->set('street', 'Updated Street')
            ->set('house', '9B')
            ->set('lat', 50.46)
            ->set('lng', 30.53)
            ->call('save')
            ->assertDispatched('address-saved')
            ->assertDispatched('sheet:close', name: 'addressForm');

        $this->assertDatabaseHas('client_addresses', [
            'id' => $address->id,
            'user_id' => $user->id,
            'label' => 'work',
            'title' => 'Офіс',
            'building_type' => 'house',
            'address_text' => 'Updated Street, Kyiv',
            'street' => 'Updated Street',
            'house' => '9B',
            'entrance' => null,
            'apartment' => null,
        ]);

        $this->assertDatabaseHas('client_addresses', [
            'id' => $foreignAddress->id,
            'user_id' => $otherUser->id,
            'street' => 'Foreign Street',
            'house' => '2',
        ]);
    }

    public function test_it_cannot_update_another_users_address(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $foreignAddress = ClientAddress::create([
            'user_id' => $otherUser->id,
            'label' => 'work',
            'title' => 'Foreign office',
            'building_type' => 'house',
            'address_text' => 'Foreign Address',
            'city' => 'Lviv',
            'street' => 'Foreign Street',
            'house' => '2',
            'lat' => 49.84,
            'lng' => 24.03,
        ]);

        $this->actingAs($user);

        Livewire::test(AddressForm::class)
            ->set('addressId', $foreignAddress->id)
            ->set('label', 'home')
            ->set('building_type', 'house')
            ->set('search', 'Attempted takeover, Kyiv')
            ->set('city', 'Kyiv')
            ->set('street', 'Attempted takeover')
            ->set('house', '7')
            ->set('lat', 50.45)
            ->set('lng', 30.52)
            ->call('save')
            ->assertHasErrors(['search']);

        $this->assertDatabaseHas('client_addresses', [
            'id' => $foreignAddress->id,
            'user_id' => $otherUser->id,
            'label' => 'work',
            'title' => 'Foreign office',
            'address_text' => 'Foreign Address',
            'city' => 'Lviv',
            'street' => 'Foreign Street',
            'house' => '2',
        ]);
    }

    public function test_it_requires_apartment_details_for_apartment_addresses(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(AddressForm::class)
            ->set('label', 'home')
            ->set('building_type', 'apartment')
            ->set('search', 'Main Street, Kyiv')
            ->set('city', 'Kyiv')
            ->set('street', 'Main Street')
            ->set('house', '7A')
            ->set('lat', 50.45)
            ->set('lng', 30.52)
            ->set('entrance', null)
            ->set('floor', null)
            ->set('apartment', null)
            ->call('save')
            ->assertHasErrors(['entrance' => ['required_if'], 'floor' => ['required_if'], 'apartment' => ['required_if']])
            ->assertDispatched('notify', type: 'error', message: 'Для квартири заповніть підʼїзд, поверх і квартиру.');
    }

    public function test_it_allows_private_house_without_apartment_details(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(AddressForm::class)
            ->set('label', 'home')
            ->set('building_type', 'house')
            ->set('search', 'Main Street, Kyiv')
            ->set('city', 'Kyiv')
            ->set('street', 'Main Street')
            ->set('house', '7A')
            ->set('lat', 50.45)
            ->set('lng', 30.52)
            ->set('entrance', null)
            ->set('floor', null)
            ->set('apartment', null)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('address-saved');

        $this->assertDatabaseHas('client_addresses', [
            'user_id' => $user->id,
            'building_type' => 'house',
            'entrance' => null,
            'floor' => null,
            'apartment' => null,
        ]);
    }

    public function test_it_requires_coordinates_with_the_documented_search_error_message(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(AddressForm::class)
            ->set('label', 'home')
            ->set('building_type', 'house')
            ->set('search', 'Main Street, Kyiv')
            ->set('city', 'Kyiv')
            ->set('street', 'Main Street')
            ->set('house', '7A')
            ->set('lat', null)
            ->set('lng', null)
            ->call('save')
            ->assertHasErrors(['search' => ['custom']])
            ->assertSee('Уточніть точку на мапі.');
    }
}
