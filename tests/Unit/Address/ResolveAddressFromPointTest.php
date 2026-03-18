<?php

namespace Tests\Unit\Address;

use App\DTO\Address\AddressPointData;
use App\Services\Address\ResolveAddressFromPoint;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResolveAddressFromPointTest extends TestCase
{
    public function test_it_maps_nominatim_payload_and_builds_search_text(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'address' => [
                    'road' => ' 12, Main Street ',
                    'house_number' => ' 7A ',
                    'city' => ' Kyiv ',
                    'state' => ' Kyiv region ',
                ],
            ]),
        ]);

        $resolved = app(ResolveAddressFromPoint::class)->execute(new AddressPointData(50.45, 30.52, 'map'));

        $this->assertNotNull($resolved);
        $this->assertSame('Main Street', $resolved->street);
        $this->assertSame('7A', $resolved->house);
        $this->assertSame('Kyiv', $resolved->city);
        $this->assertSame('Kyiv region', $resolved->region);
        $this->assertSame('Main Street 7A, Kyiv, Kyiv region', $resolved->search);
    }

    public function test_it_falls_back_to_display_name_when_house_number_is_missing(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'display_name' => 'Ukraine, Lviv, Shevchenka Street, 15B, district',
                'address' => [
                    'pedestrian' => 'Shevchenka Street',
                    'town' => 'Lviv',
                    'region' => 'Lviv region',
                ],
            ]),
        ]);

        $resolved = app(ResolveAddressFromPoint::class)->execute(new AddressPointData(49.84, 24.03, 'map'));

        $this->assertNotNull($resolved);
        $this->assertSame('15B', $resolved->house);
        $this->assertSame('Shevchenka Street 15B, Lviv, Lviv region', $resolved->search);
    }

    public function test_it_returns_null_for_bad_or_malformed_reverse_geocode_payloads(): void
    {
        $service = app(ResolveAddressFromPoint::class);

        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([], 500),
        ]);
        $this->assertNull($service->execute(new AddressPointData(50.45, 30.52, 'map')));

        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response(['unexpected' => 'payload']),
        ]);
        $this->assertNull($service->execute(new AddressPointData(50.45, 30.52, 'map')));

        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => fn () => throw new \RuntimeException('boom'),
        ]);
        $this->assertNull($service->execute(new AddressPointData(50.45, 30.52, 'map')));
    }
}
