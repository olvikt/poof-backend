<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GeocodeController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json([]);
        }

        $lat = $this->normalizedCoordinate($request->query('lat'), -90, 90);
        $lng = $this->normalizedCoordinate($request->query('lng'), -180, 180);

        $cacheKey = 'geocode_' . md5(mb_strtolower($query));

        try {
            $suggestions = Cache::remember($cacheKey, 3600, function () use ($query, $lat, $lng): array {
                return $this->fetchPhotonSuggestions($query, $lat, $lng);
            });
        } catch (\Throwable) {
            $suggestions = [];
        }

        return response()->json(is_array($suggestions) ? $suggestions : []);
    }

    private function fetchPhotonSuggestions(string $query, ?float $lat, ?float $lng): array
    {
        $params = [
            'q' => $query,
            'limit' => 5,
            'lang' => 'uk',
            'countrycode' => 'ua',
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

        $data = $response->json();

        if (! is_array($data)) {
            return [];
        }

        $features = $data['features'] ?? null;

        if (! is_array($features)) {
            return [];
        }

        return collect($features)
            ->take(5)
            ->map(function ($feature): ?array {
                if (! is_array($feature)) {
                    return null;
                }

                $properties = $feature['properties'] ?? [];
                $coordinates = $feature['geometry']['coordinates'] ?? [null, null];

                $lng = isset($coordinates[0]) && is_numeric($coordinates[0])
                    ? (float) $coordinates[0]
                    : null;
                $lat = isset($coordinates[1]) && is_numeric($coordinates[1])
                    ? (float) $coordinates[1]
                    : null;

                if ($lat === null || $lng === null) {
                    return null;
                }

                $street = $this->nullableString($properties['street'] ?? $properties['name'] ?? $properties['district'] ?? $properties['locality'] ?? null);
                $house = $this->nullableString($properties['housenumber'] ?? null);
                $city = $this->nullableString($properties['city'] ?? $properties['county'] ?? $properties['state'] ?? null);

                $line1 = trim(implode(' ', array_filter([$street, $house])));
                $label = trim(implode(', ', array_filter([$line1, $city])));

                return [
                    'label' => $label !== '' ? $label : ($street ?? $city ?? ''),
                    'street' => $street,
                    'city' => $city,
                    'house' => $house,
                    'line1' => $line1 !== '' ? $line1 : null,
                    'line2' => $city,
                    'lat' => $lat,
                    'lng' => $lng,
                ];
            })
            ->filter()
            ->values()
            ->all();
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
