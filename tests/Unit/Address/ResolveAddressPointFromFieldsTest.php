<?php

namespace Tests\Unit\Address;

use App\DTO\Address\AddressFieldsData;
use App\Services\Address\ResolveAddressPointFromFields;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResolveAddressPointFromFieldsTest extends TestCase
{
    public function test_it_returns_null_when_house_is_empty(): void
    {
        Http::fake();

        $resolved = app(ResolveAddressPointFromFields::class)->execute(new AddressFieldsData(
            street: 'Main Street',
            house: '   ',
            city: 'Kyiv',
            search: 'Main Street, Kyiv',
            lat: 50.45,
            lng: 30.52,
        ));

        $this->assertNull($resolved);
        Http::assertNothingSent();
    }

    public function test_it_returns_null_when_street_stays_empty_after_search_fallback(): void
    {
        Http::fake();

        $resolved = app(ResolveAddressPointFromFields::class)->execute(new AddressFieldsData(
            street: ' ',
            house: '7A',
            city: 'Kyiv',
            search: '   ',
            lat: 50.45,
            lng: 30.52,
        ));

        $this->assertNull($resolved);
        Http::assertNothingSent();
    }

    public function test_it_uses_street_and_city_from_search_fallback_and_builds_current_query_shape(): void
    {
        Http::fake([
            $this->geocodeUrlPattern() => Http::response([
                ['lat' => '50.4501', 'lng' => '30.5234'],
            ]),
        ]);

        $resolved = app(ResolveAddressPointFromFields::class)->execute(new AddressFieldsData(
            street: ' ',
            house: ' 7A ',
            city: ' ',
            search: ' Main Street , Kyiv , Ukraine ',
            lat: 50.45,
            lng: 30.52,
        ));

        $this->assertNotNull($resolved);
        $this->assertSame(50.4501, $resolved->lat);
        $this->assertSame(30.5234, $resolved->lng);
        $this->assertSame('Main Street, 7A, Kyiv', $resolved->query);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return str_starts_with($request->url(), url('/api/geocode'))
                && ($data['q'] ?? null) === 'Main Street, 7A, Kyiv'
                && ($data['lat'] ?? null) === '50.45'
                && ($data['lng'] ?? null) === '30.52';
        });
    }

    public function test_it_keeps_explicit_city_when_search_fallback_provides_another_city(): void
    {
        Http::fake([
            $this->geocodeUrlPattern() => Http::response([
                ['lat' => '50.4501', 'lng' => '30.5234'],
            ]),
        ]);

        $resolved = app(ResolveAddressPointFromFields::class)->execute(new AddressFieldsData(
            street: ' ',
            house: '7A',
            city: 'Lviv',
            search: 'Main Street, Kyiv, Ukraine',
            lat: 50.45,
            lng: 30.52,
        ));

        $this->assertNotNull($resolved);
        $this->assertSame('Main Street, 7A, Lviv', $resolved->query);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return str_starts_with($request->url(), url('/api/geocode'))
                && ($data['q'] ?? null) === 'Main Street, 7A, Lviv';
        });
    }

    public function test_it_builds_query_without_city_when_city_is_missing_in_fields_and_search_fallback(): void
    {
        Http::fake([
            $this->geocodeUrlPattern() => Http::response([
                ['lat' => '50.4501', 'lng' => '30.5234'],
            ]),
        ]);

        $resolved = app(ResolveAddressPointFromFields::class)->execute(new AddressFieldsData(
            street: 'Main Street',
            house: '7A',
            city: ' ',
            search: null,
            lat: null,
            lng: null,
        ));

        $this->assertNotNull($resolved);
        $this->assertSame('Main Street, 7A', $resolved->query);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return str_starts_with($request->url(), url('/api/geocode'))
                && ($data['q'] ?? null) === 'Main Street, 7A'
                && ! array_key_exists('lat', $data)
                && ! array_key_exists('lng', $data);
        });
    }

    public function test_it_returns_coordinates_from_valid_geocode_response(): void
    {
        Http::fake([
            $this->geocodeUrlPattern() => Http::response([
                ['lat' => '49.8397', 'lng' => '24.0297'],
            ]),
        ]);

        $resolved = app(ResolveAddressPointFromFields::class)->execute(new AddressFieldsData(
            street: 'Shevchenka Street',
            house: '15B',
            city: 'Lviv',
            search: null,
            lat: null,
            lng: null,
        ));

        $this->assertNotNull($resolved);
        $this->assertSame(49.8397, $resolved->lat);
        $this->assertSame(24.0297, $resolved->lng);
        $this->assertSame('Shevchenka Street, 15B, Lviv', $resolved->query);
    }


    private function geocodeUrlPattern(): string
    {
        return url('/api/geocode') . '*';
    }

    public function test_it_silently_returns_null_for_bad_or_malformed_responses(): void
    {
        $service = app(ResolveAddressPointFromFields::class);

        Http::fake([
            $this->geocodeUrlPattern() => Http::response([], 500),
        ]);
        $this->assertNull($service->execute(new AddressFieldsData('Main Street', '7A', 'Kyiv', null, 50.45, 30.52)));

        Http::fake([
            $this->geocodeUrlPattern() => Http::response([
                ['lat' => '50.45'],
            ]),
        ]);
        $this->assertNull($service->execute(new AddressFieldsData('Main Street', '7A', 'Kyiv', null, 50.45, 30.52)));

        Http::fake([
            $this->geocodeUrlPattern() => Http::response([
                'unexpected' => 'payload',
            ]),
        ]);
        $this->assertNull($service->execute(new AddressFieldsData('Main Street', '7A', 'Kyiv', null, 50.45, 30.52)));

        Http::fake([
            $this->geocodeUrlPattern() => fn () => throw new \RuntimeException('boom'),
        ]);
        $this->assertNull($service->execute(new AddressFieldsData('Main Street', '7A', 'Kyiv', null, 50.45, 30.52)));
    }
}
