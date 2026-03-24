<?php

namespace App\Services\Geocoding;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class NominatimReverseGeocodeService
{
    public function fetchSuggestions(float $lat, float $lng): array
    {
        try {
            $response = Http::timeout(5)
                ->retry(2, 100)
                ->acceptJson()
                ->withHeaders([
                    'User-Agent' => config('app.name', 'Poof') . '/1.0',
                ])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'json',
                    'lat' => $lat,
                    'lon' => $lng,
                    'addressdetails' => 1,
                ]);
        } catch (ConnectionException|RequestException|\Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return [];
        }

        $address = is_array($payload['address'] ?? null) ? $payload['address'] : [];

        $street = $this->nullableString(
            $address['road'] ?? $address['pedestrian'] ?? $address['street'] ?? null
        );

        $city = $this->nullableString(
            $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['county'] ?? null
        );

        $house = $this->nullableString($address['house_number'] ?? null);
        $region = $this->nullableString($address['state'] ?? $address['region'] ?? null);

        $label = trim(implode(' ', array_filter([$street, $house])));
        if ($label === '') {
            $label = $this->nullableString($payload['display_name'] ?? null) ?? 'Unknown location';
        }

        $line1 = trim(implode(' ', array_filter([$street, $house])));
        $line2 = trim(implode(', ', array_filter([$city, $region])));

        return [[
            'label' => $label,
            'street' => $street,
            'house' => $house,
            'city' => $city,
            'region' => $region,
            'line1' => $line1 !== '' ? $line1 : null,
            'line2' => $line2 !== '' ? $line2 : null,
            'lat' => round($lat, 6),
            'lng' => round($lng, 6),
        ]];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
