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
            ->assertSet('street', 'Updated Street')
            ->assertSet('house', '9B')
            ->assertSet('lat', 50.46)
            ->assertSet('lng', 30.53)
            ->assertDispatched('sheet:open', name: 'addressForm')
            ->assertDispatched('map:set-marker', lat: 50.46, lng: 30.53);
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

    public function test_it_requires_coordinates_with_current_validation_message(): void
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
            ->assertHasErrors(['search' => ['custom']]);
    }
}
