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
}
