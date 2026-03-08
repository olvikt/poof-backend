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

        if (mb_strlen($query) < 2) {
            return response()->json([]);
        }

        $lat = $this->normalizedCoordinate($request->query('lat'), -90, 90);
        $lng = $this->normalizedCoordinate($request->query('lng'), -180, 180);

        try {
            $suggestions = $this->fetchPhotonSuggestions($query, $lat, $lng);
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

        $data = $response->json() ?? [];

        $results = collect($data['features'] ?? [])
            ->map(function ($feature) {
                $geometry = $feature['geometry'] ?? [];
                $coords = $geometry['coordinates'] ?? null;

                if (! is_array($coords) || count($coords) < 2) {
                    return null;
                }

                $lng = (float) $coords[0];
                $lat = (float) $coords[1];

                if (! is_finite($lat) || ! is_finite($lng)) {
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

                $labelParts = array_filter([
                    $street ?: $name,
                    $housenumber,
                ]);

                $label = implode(' ', $labelParts);

                if (! $label) {
                    $label = $name ?? $city ?? 'Unknown location';
                }

                return [
                    'label' => $label,
                    'street' => $street ?? $name,
                    'city' => $city,
                    'lat' => $lat,
                    'lng' => $lng,
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
