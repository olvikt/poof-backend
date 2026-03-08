<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GeocodeController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $lat = $this->normalizedCoordinate($request->query('lat'), -90, 90);
        $lng = $this->normalizedCoordinate($request->query('lng'), -180, 180);

        if ($query === '' && $lat !== null && $lng !== null) {
            try {
                $suggestions = $this->fetchReverseSuggestions($lat, $lng);
            } catch (\Throwable) {
                $suggestions = [];
            }

            return response()->json(is_array($suggestions) ? $suggestions : []);
        }

        if (mb_strlen($query) < 2) {
            return response()->json([]);
        }

        try {
            $suggestions = $this->fetchPhotonSuggestions($query, $lat, $lng);
        } catch (\Throwable) {
            $suggestions = [];
        }

        return response()->json(is_array($suggestions) ? $suggestions : []);
    }


    private function fetchReverseSuggestions(float $lat, float $lng): array
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

        $label = trim(implode(' ', array_filter([$street, $house])));
        if ($label === '') {
            $label = $this->nullableString($payload['display_name'] ?? null) ?? 'Unknown location';
        }

        return [[
            'label' => $label,
            'street' => $street,
            'city' => $city,
            'lat' => round($lat, 6),
            'lng' => round($lng, 6),
        ]];
    }

    private function fetchPhotonSuggestions(string $query, ?float $lat, ?float $lng): array
    {
        $params = [
            'q' => $query,
            'limit' => 5,
            'lang' => 'uk',
            'bbox' => '22,44,40,52',
        ];

        if ($lat !== null && $lng !== null) {
            $params['lat'] = $lat;
            $params['lon'] = $lng;
        }

        try {
            $response = Http::timeout(5)
                ->retry(2, 100)
                ->acceptJson()
                ->get('https://photon.komoot.io/api', $params);
        } catch (ConnectionException|RequestException|\Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $data = $response->json() ?? [];

        $results = collect($data['features'] ?? [])
            ->map(function ($feature) {

                if (!is_array($feature)) {
                    return null;
                }

                $geometry = $feature['geometry'] ?? null;

                if (!is_array($geometry)) {
                    return null;
                }

                $coords = $geometry['coordinates'] ?? null;

                if (!is_array($coords) || count($coords) < 2) {
                    return null;
                }

                $lng = (float) $coords[0];
                $lat = (float) $coords[1];

                if (!$lat || !$lng) {
                    return null;
                }

                $props = $feature['properties'] ?? [];

                $name = $props['name'] ?? null;
                $street = $props['street'] ?? null;
                $housenumber = $props['housenumber'] ?? null;

                $city =
                    $props['city'] ??
                    $props['county'] ??
                    $props['state'] ??
                    null;

                $label = trim(
                    implode(' ', array_filter([
                        $street ?: $name,
                        $housenumber
                    ]))
                );

                if (!$label) {
                    $label = $name ?? $city ?? 'Unknown location';
                }

                return [
                    'label' => $label,
                    'street' => $street ?? $name,
                    'city' => $city,
                    'lat' => $lat,
                    'lng' => $lng
                ];
            })
            ->filter()
            ->unique('label')
            ->take(5)
            ->values()
            ->all();

        return $results;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function normalizedCoordinate(mixed $value, float $min, float $max): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $coordinate = (float) $value;

        if ($coordinate < $min || $coordinate > $max) {
            return null;
        }

        return round($coordinate, 6);
    }
}
