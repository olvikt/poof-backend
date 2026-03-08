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

        $features = $data['features'] ?? null;

        if (! is_array($features)) {
            return [];
        }

        return collect($features)
            ->take(5)
            ->map(function ($item): ?array {
                if (! is_array($item)) {
                    return null;
                }

                $props = is_array($item['properties'] ?? null) ? $item['properties'] : [];
                $coords = $item['geometry']['coordinates'] ?? [];

                if (! is_array($coords) || count($coords) < 2) {
                    return null;
                }

                $lng = is_numeric($coords[0] ?? null) ? (float) $coords[0] : null;
                $lat = is_numeric($coords[1] ?? null) ? (float) $coords[1] : null;

                if ($lat === null || $lng === null) {
                    return null;
                }

                $name = $this->nullableString($props['name'] ?? null) ?? '';
                $street = $this->nullableString($props['street'] ?? null) ?? '';
                $city = $this->nullableString($props['city'] ?? $props['county'] ?? null) ?? '';

                $label = collect([$name, $street, $city])
                    ->filter()
                    ->implode(', ');

                return [
                    'label' => $label,
                    'street' => $street !== '' ? $street : $name,
                    'city' => $city,
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
