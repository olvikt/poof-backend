<?php

namespace Tests\Unit\Geocoding;

use App\Services\Geocoding\NominatimReverseGeocodeService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NominatimReverseGeocodeServiceTest extends TestCase
{
    public function test_it_normalizes_reverse_payload_into_suggestion_format(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'address' => [
                    'road' => 'Мандриківська',
                    'house_number' => '23',
                    'city' => 'Дніпро',
                    'state' => 'Дніпропетровська область',
                ],
            ]),
        ]);

        $result = app(NominatimReverseGeocodeService::class)->fetchSuggestions(48.4572234, 35.0308123);

        $this->assertSame([[
            'label' => 'Мандриківська 23',
            'street' => 'Мандриківська',
            'house' => '23',
            'city' => 'Дніпро',
            'region' => 'Дніпропетровська область',
            'line1' => 'Мандриківська 23',
            'line2' => 'Дніпро, Дніпропетровська область',
            'lat' => 48.457223,
            'lng' => 35.030812,
        ]], $result);
    }

    public function test_it_falls_back_to_display_name_or_unknown_location_when_address_is_sparse(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'display_name' => 'Ukraine, Kyiv',
                'address' => [],
            ]),
        ]);

        $result = app(NominatimReverseGeocodeService::class)->fetchSuggestions(50.45, 30.52);

        $this->assertSame('Ukraine, Kyiv', $result[0]['label']);
        $this->assertNull($result[0]['line1']);
        $this->assertNull($result[0]['line2']);

        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'address' => [],
            ]),
        ]);

        $fallbackResult = app(NominatimReverseGeocodeService::class)->fetchSuggestions(50.45, 30.52);

        $this->assertSame('Unknown location', $fallbackResult[0]['label']);
    }

    public function test_it_returns_empty_array_for_bad_or_malformed_upstream_responses(): void
    {
        $service = app(NominatimReverseGeocodeService::class);

        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([], 503),
        ]);
        $this->assertSame([], $service->fetchSuggestions(48.45, 35.03));

        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response('not-json-payload', 200),
        ]);
        $this->assertSame([], $service->fetchSuggestions(48.45, 35.03));

        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => fn () => throw new \RuntimeException('boom'),
        ]);
        $this->assertSame([], $service->fetchSuggestions(48.45, 35.03));
    }
}
