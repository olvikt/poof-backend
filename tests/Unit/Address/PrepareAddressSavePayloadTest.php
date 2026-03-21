<?php

namespace Tests\Unit\Address;

use App\DTO\Address\AddressFormData;
use App\Services\Address\PrepareAddressSavePayload;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PrepareAddressSavePayloadTest extends TestCase
{
    public function test_it_derives_street_and_city_from_search_and_builds_canonical_payload(): void
    {
        Carbon::setTestNow('2026-03-21 12:34:56');

        $service = new PrepareAddressSavePayload();
        $data = new AddressFormData(
            addressId: null,
            label: 'home',
            title: 'Test',
            buildingType: 'house',
            search: '12, Main Street, Kyiv',
            lat: 50.45,
            lng: 30.52,
            city: null,
            region: 'Kyiv region',
            street: null,
            house: '  7A ',
            entrance: '1',
            intercom: '22',
            floor: '3',
            apartment: '15',
        );

        $fallback = $service->applyFallback($data);
        $payload = $service->execute($data)->toArray();

        $this->assertSame('Main Street', $fallback['street']);
        $this->assertSame('Kyiv', $fallback['city']);
        $this->assertSame('Main Street', $payload['street']);
        $this->assertSame('Kyiv', $payload['city']);
        $this->assertSame('7A', $payload['house']);
        $this->assertNull($payload['entrance']);
        $this->assertNull($payload['intercom']);
        $this->assertNull($payload['floor']);
        $this->assertNull($payload['apartment']);
        $this->assertSame('12, Main Street, Kyiv', $payload['address_text']);
        $this->assertSame('manual', $payload['geocode_source']);
        $this->assertSame('exact', $payload['geocode_accuracy']);
        $this->assertTrue(Carbon::now()->equalTo($payload['geocoded_at']));

        Carbon::setTestNow();
    }

    public function test_it_does_not_override_existing_city_when_using_search_fallback(): void
    {
        $service = new PrepareAddressSavePayload();
        $data = new AddressFormData(
            addressId: null,
            label: 'work',
            title: null,
            buildingType: 'apartment',
            search: 'Street Name, Lviv',
            lat: 49.84,
            lng: 24.03,
            city: 'Odesa',
            region: null,
            street: null,
            house: '10',
            entrance: '2',
            intercom: '5',
            floor: '6',
            apartment: '42',
        );

        $payload = $service->execute($data)->toArray();

        $this->assertSame('Street Name', $payload['street']);
        $this->assertSame('Odesa', $payload['city']);
        $this->assertSame('2', $payload['entrance']);
        $this->assertSame('42', $payload['apartment']);
    }
}
