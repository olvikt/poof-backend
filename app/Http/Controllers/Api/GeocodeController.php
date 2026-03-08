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

        $data = $response->json();

        if (! is_array($data)) {
            return [];
        }

        $results = collect($data['features'] ?? [])
            ->map(function ($feature) {

                $coords = $feature['geometry']['coordinates'] ?? null;

                if (! $coords || ! is_array($coords) || count($coords) < 2) {
                    return null;
                }

                $props = $feature['properties'] ?? [];

                $name = $props['name'] ?? '';
                $street = $props['street'] ?? $name;

                $city =
                    $props['city'] ??
                    $props['county'] ??
                    $props['state'] ??
                    '';

                $label = trim(
                    ($props['street'] ?? $name) .
                    ' ' .
                    ($props['housenumber'] ?? '')
                );

                if (! $label) {
                    $label = $name;
                }

                return [
                    'label' => $label,
                    'street' => $street,
                    'city' => $city,
                    'lat' => (float) $coords[1],
                    'lng' => (float) $coords[0],
                ];
            })
            ->filter(fn ($item) => $item !== null)
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
