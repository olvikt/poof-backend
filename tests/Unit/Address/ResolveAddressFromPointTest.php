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


    public function test_it_preserves_house_and_corpus_formats_from_house_number(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'address' => [
                    'road' => 'Набережна Перемоги',
                    'house_number' => '108 корпус 5',
                    'city' => 'Дніпро',
                    'state' => 'Дніпропетровська область',
                ],
            ]),
        ]);

        $resolved = app(ResolveAddressFromPoint::class)->execute(new AddressPointData(48.43, 35.07, 'map'));

        $this->assertNotNull($resolved);
        $this->assertSame('108 к5', $resolved->house);
        $this->assertSame('Набережна Перемоги 108 к5, Дніпро, Дніпропетровська область', $resolved->search);
    }

    public function test_it_extracts_house_and_corpus_from_display_name_when_house_number_is_missing(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'display_name' => 'Україна, Дніпро, Соборний район, Набережна Перемоги, 108 к 4, житловий масив Перемога-5',
                'address' => [
                    'road' => 'Набережна Перемоги',
                    'city' => 'Дніпро',
                    'state' => 'Дніпропетровська область',
                ],
            ]),
        ]);

        $resolved = app(ResolveAddressFromPoint::class)->execute(new AddressPointData(48.43, 35.07, 'map'));

        $this->assertNotNull($resolved);
        $this->assertSame('108 к4', $resolved->house);
        $this->assertSame('Набережна Перемоги 108 к4, Дніпро, Дніпропетровська область', $resolved->search);
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
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'address' => 'not-an-array',
            ]),
        ]);
        $this->assertNull($service->execute(new AddressPointData(50.45, 30.52, 'map')));

        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => fn () => throw new \RuntimeException('boom'),
        ]);
        $this->assertNull($service->execute(new AddressPointData(50.45, 30.52, 'map')));
    }

    public function test_it_preserves_canonical_search_text_from_payload_label(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'label' => '  Custom Canonical Search Text  ',
                'address' => [
                    'road' => 'Main Street',
                    'house_number' => '7',
                    'city' => 'Kyiv',
                    'state' => 'Kyiv region',
                ],
            ]),
        ]);

        $resolved = app(ResolveAddressFromPoint::class)->execute(new AddressPointData(50.45, 30.52, 'map'));

        $this->assertNotNull($resolved);
        $this->assertSame('Main Street', $resolved->street);
        $this->assertSame('7', $resolved->house);
        $this->assertSame('Kyiv', $resolved->city);
        $this->assertSame('Kyiv region', $resolved->region);
        $this->assertSame('Custom Canonical Search Text', $resolved->search);
    }
}
