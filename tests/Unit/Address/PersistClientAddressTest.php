<?php

namespace Tests\Unit\Address;

use App\Actions\Address\PersistClientAddress;
use App\DTO\Address\AddressFormData;
use App\DTO\Address\PersistAddressData;
use App\Models\ClientAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersistClientAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_new_client_address_for_the_authenticated_user_with_canonical_payload(): void
    {
        $user = User::factory()->create();

        $payload = PersistAddressData::fromCanonical([
            'label' => 'home',
            'title' => 'My place',
            'building_type' => 'house',
            'address_text' => 'Main Street 7A, Kyiv',
            'city' => 'Kyiv',
            'region' => 'Kyiv region',
            'street' => 'Main Street',
            'house' => '7A',
            'lat' => 50.45,
            'lng' => 30.52,
            'entrance' => null,
            'intercom' => null,
            'floor' => null,
            'apartment' => null,
            'geocode_source' => 'manual',
            'geocode_accuracy' => 'exact',
            'is_default' => true,
        ]);

        app(PersistClientAddress::class)->execute(
            $this->makeFormData(addressId: null),
            $payload,
            $user->id,
        );

        $this->assertDatabaseHas('client_addresses', [
            'user_id' => $user->id,
            'label' => 'home',
            'title' => 'My place',
            'building_type' => 'house',
            'address_text' => 'Main Street 7A, Kyiv',
            'city' => 'Kyiv',
            'region' => 'Kyiv region',
            'street' => 'Main Street',
            'house' => '7A',
            'lat' => 50.45,
            'lng' => 30.52,
            'is_default' => 1,
        ]);
    }

    public function test_it_ignores_user_id_from_payload_and_persists_create_under_authenticated_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $payload = PersistAddressData::fromCanonical([
            'user_id' => $otherUser->id,
            'label' => 'home',
            'title' => 'Auth-bound create',
            'building_type' => 'house',
            'street' => 'Boundary Street',
            'house' => '42',
        ]);

        app(PersistClientAddress::class)->execute(
            $this->makeFormData(addressId: null),
            $payload,
            $user->id,
        );

        $this->assertDatabaseHas('client_addresses', [
            'user_id' => $user->id,
            'label' => 'home',
            'title' => 'Auth-bound create',
            'street' => 'Boundary Street',
            'house' => '42',
        ]);

        $this->assertDatabaseMissing('client_addresses', [
            'user_id' => $otherUser->id,
            'title' => 'Auth-bound create',
            'street' => 'Boundary Street',
            'house' => '42',
        ]);
    }

    public function test_it_updates_only_the_owned_address_in_edit_mode_without_mutating_other_records(): void
    {
        $user = User::factory()->create();

        $ownedAddress = ClientAddress::query()->create([
            'user_id' => $user->id,
            'label' => 'home',
            'title' => 'Old title',
            'building_type' => 'apartment',
            'address_text' => 'Old text',
            'city' => 'Kyiv',
            'region' => 'Kyiv region',
            'street' => 'Old Street',
            'house' => '1',
            'lat' => 50.44,
            'lng' => 30.51,
            'is_default' => false,
        ]);

        $unrelatedAddress = ClientAddress::query()->create([
            'user_id' => $user->id,
            'label' => 'work',
            'title' => 'Do not touch',
            'building_type' => 'apartment',
            'address_text' => 'Unrelated text',
            'city' => 'Lviv',
            'region' => 'Lviv region',
            'street' => 'Unrelated Street',
            'house' => '9',
            'lat' => 49.84,
            'lng' => 24.03,
            'is_default' => true,
        ]);

        $payload = PersistAddressData::fromCanonical([
            'label' => 'work',
            'title' => 'Updated title',
            'building_type' => 'house',
            'address_text' => 'Updated text',
            'city' => 'Odesa',
            'region' => 'Odesa region',
            'street' => 'Updated Street',
            'house' => '77B',
            'lat' => 46.48,
            'lng' => 30.73,
            'is_default' => true,
        ]);

        app(PersistClientAddress::class)->execute(
            $this->makeFormData(addressId: $ownedAddress->id),
            $payload,
            $user->id,
        );

        $this->assertDatabaseHas('client_addresses', [
            'id' => $ownedAddress->id,
            'user_id' => $user->id,
            'label' => 'work',
            'title' => 'Updated title',
            'building_type' => 'house',
            'address_text' => 'Updated text',
            'city' => 'Odesa',
            'region' => 'Odesa region',
            'street' => 'Updated Street',
            'house' => '77B',
            'lat' => 46.48,
            'lng' => 30.73,
            'is_default' => 1,
        ]);

        $this->assertDatabaseHas('client_addresses', [
            'id' => $unrelatedAddress->id,
            'user_id' => $user->id,
            'label' => 'work',
            'title' => 'Do not touch',
            'building_type' => 'apartment',
            'address_text' => 'Unrelated text',
            'city' => 'Lviv',
            'street' => 'Unrelated Street',
            'house' => '9',
            'lat' => 49.84,
            'lng' => 24.03,
            'is_default' => 1,
        ]);
    }

    public function test_it_rejects_updates_for_addresses_owned_by_another_user(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $victimAddress = ClientAddress::query()->create([
            'user_id' => $owner->id,
            'label' => 'home',
            'title' => 'Victim address',
            'building_type' => 'apartment',
            'street' => 'Safe Street',
            'house' => '5',
        ]);

        $payload = PersistAddressData::fromCanonical([
            'label' => 'work',
            'title' => 'Hacked',
            'building_type' => 'house',
            'street' => 'Bad Street',
            'house' => '999',
            'is_default' => true,
        ]);

        $this->expectException(ModelNotFoundException::class);

        try {
            app(PersistClientAddress::class)->execute(
                $this->makeFormData(addressId: $victimAddress->id),
                $payload,
                $attacker->id,
            );
        } finally {
            $this->assertDatabaseHas('client_addresses', [
                'id' => $victimAddress->id,
                'user_id' => $owner->id,
                'label' => 'home',
                'title' => 'Victim address',
                'building_type' => 'apartment',
                'street' => 'Safe Street',
                'house' => '5',
                'is_default' => 0,
            ]);
        }
    }

    private function makeFormData(?int $addressId): AddressFormData
    {
        return new AddressFormData(
            addressId: $addressId,
            label: 'home',
            title: 'Any',
            buildingType: 'apartment',
            search: null,
            lat: null,
            lng: null,
            city: null,
            region: null,
            street: null,
            house: null,
            entrance: null,
            intercom: null,
            floor: null,
            apartment: null,
        );
    }
}
