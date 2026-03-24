<?php

namespace Tests\Unit\Address;

use App\Services\Address\FilterClientAddressPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FilterClientAddressPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_keeps_only_real_client_address_columns(): void
    {
        $service = new FilterClientAddressPayload();

        $payload = $service->execute([
            'label' => 'home',
            'street' => 'Main Street',
            'house' => '7A',
            'lat' => 50.45,
            'lng' => 30.52,
            'not_a_column' => 'drop-me',
        ]);

        $columns = Schema::getColumnListing('client_addresses');

        $this->assertSame([
            'label' => 'home',
            'street' => 'Main Street',
            'house' => '7A',
            'lat' => 50.45,
            'lng' => 30.52,
        ], $payload);
        $this->assertContains('label', $columns);
        $this->assertNotContains('not_a_column', array_keys($payload));
    }

    public function test_it_drops_noisy_runtime_fields_but_keeps_canonical_address_payload(): void
    {
        $service = new FilterClientAddressPayload();

        $payload = $service->execute([
            'label' => 'work',
            'building_type' => 'apartment',
            'street' => 'Khreshchatyk',
            'house' => '10B',
            'apartment' => '42',
            'entrance' => '2',
            'floor' => '6',
            'city' => 'Kyiv',
            'region' => 'Kyiv region',
            'lat' => 50.4501,
            'lng' => 30.5234,
            'search' => '10B, Khreshchatyk, Kyiv',
            'comment' => 'leave at the door',
            'source' => 'manual-ui-state',
            'selectedAddressLocked' => true,
        ]);

        $this->assertSame([
            'label' => 'work',
            'building_type' => 'apartment',
            'street' => 'Khreshchatyk',
            'house' => '10B',
            'apartment' => '42',
            'entrance' => '2',
            'floor' => '6',
            'city' => 'Kyiv',
            'region' => 'Kyiv region',
            'lat' => 50.4501,
            'lng' => 30.5234,
        ], $payload);
        $this->assertArrayNotHasKey('search', $payload);
        $this->assertArrayNotHasKey('comment', $payload);
        $this->assertArrayNotHasKey('source', $payload);
        $this->assertArrayNotHasKey('selectedAddressLocked', $payload);
    }

    public function test_it_preserves_house_payload_and_null_normalized_apartment_fields_for_house_type(): void
    {
        $service = new FilterClientAddressPayload();

        $payload = $service->execute([
            'building_type' => 'house',
            'street' => 'Main Street',
            'house' => '7A',
            'entrance' => null,
            'intercom' => null,
            'floor' => null,
            'apartment' => null,
            'city' => 'Kyiv',
            'lat' => 50.45,
            'lng' => 30.52,
            'search' => '7A, Main Street, Kyiv',
        ]);

        $this->assertSame([
            'building_type' => 'house',
            'street' => 'Main Street',
            'house' => '7A',
            'entrance' => null,
            'intercom' => null,
            'floor' => null,
            'apartment' => null,
            'city' => 'Kyiv',
            'lat' => 50.45,
            'lng' => 30.52,
        ], $payload);
        $this->assertArrayNotHasKey('search', $payload);
    }

    public function test_it_drops_persistence_managed_columns_even_when_they_exist_in_client_addresses_schema(): void
    {
        $service = new FilterClientAddressPayload();

        $payload = $service->execute([
            'id' => 999,
            'user_id' => 777,
            'label' => 'home',
            'street' => 'Main Street',
            'house' => '7A',
            'is_default' => true,
            'created_at' => now()->subDay()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        $this->assertSame([
            'label' => 'home',
            'street' => 'Main Street',
            'house' => '7A',
        ], $payload);
        $this->assertArrayNotHasKey('id', $payload);
        $this->assertArrayNotHasKey('user_id', $payload);
        $this->assertArrayNotHasKey('is_default', $payload);
        $this->assertArrayNotHasKey('created_at', $payload);
        $this->assertArrayNotHasKey('updated_at', $payload);
    }

    public function test_it_preserves_canonical_geocode_fields_and_empty_or_zero_like_values_without_extra_normalization(): void
    {
        $service = new FilterClientAddressPayload();

        $payload = $service->execute([
            'label' => 'work',
            'building_type' => 'apartment',
            'street' => 'Khreshchatyk',
            'house' => '10B',
            'entrance' => '0',
            'floor' => '0',
            'apartment' => '',
            'intercom' => null,
            'geocode_source' => 'manual',
            'geocode_accuracy' => 'exact',
            'geocoded_at' => now()->toDateTimeString(),
            'source' => 'manual-ui-state',
        ]);

        $this->assertSame('manual', $payload['geocode_source']);
        $this->assertSame('exact', $payload['geocode_accuracy']);
        $this->assertArrayHasKey('geocoded_at', $payload);
        $this->assertSame('0', $payload['entrance']);
        $this->assertSame('0', $payload['floor']);
        $this->assertSame('', $payload['apartment']);
        $this->assertNull($payload['intercom']);
        $this->assertArrayNotHasKey('source', $payload);
    }
}
