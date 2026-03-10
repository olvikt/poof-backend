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
        $query = (string) $request->query('q', '');
        $normalizedQuery = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $query) ?? ''));
        $lat = $this->normalizedCoordinate($request->query('lat'), -90, 90);
        $lng = $this->normalizedCoordinate($request->query('lon', $request->query('lng')), -180, 180);

        if ($normalizedQuery === '' && $lat !== null && $lng !== null) {
            $cacheKey = 'geocode:reverse:' . md5($lat . ',' . $lng);

            try {
                $suggestions = Cache::remember(
                    $cacheKey,
                    now()->addHours(24),
                    function () use ($lat, $lng) {
                        return $this->fetchReverseSuggestions($lat, $lng);
                    }
                );
            } catch (\Throwable) {
                $suggestions = [];
            }

            return response()->json(is_array($suggestions) ? $suggestions : []);
        }

        if (mb_strlen($normalizedQuery) < 3) {
            return response()->json([]);
        }

        $cacheKey = 'geocode:photon:' . md5($normalizedQuery);

        try {
            $suggestions = Cache::remember(
                $cacheKey,
                now()->addHours(12),
                function () use ($normalizedQuery, $lat, $lng) {
                    \Log::debug('Photon cache miss', [
                        'query' => $normalizedQuery,
                        'lat' => $lat,
                        'lng' => $lng,
                    ]);

                    return $this->fetchPhotonSuggestions($normalizedQuery, $lat, $lng);
                }
            );
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

    private function fetchPhotonSuggestions(string $query, ?float $lat, ?float $lng): array
    {
        try {
            $response = Http::timeout(2)
                ->retry(1, 100)
                ->acceptJson()
                ->get('https://photon.komoot.io/api/', [
                    'q' => $query,
                    'lat' => $lat,
                    'lon' => $lng,
                    'limit' => 10,
                ]);
        } catch (ConnectionException|RequestException|\Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $data = $response->json();
        $features = collect($data['features'] ?? [])
            ->filter(function ($feature) {
                $type = $feature['properties']['type'] ?? null;

                return in_array($type, [
                    'street',
                    'house',
                    'housenumber',
                ]);
            })
            ->values()
            ->all();

        if (empty($features)) {
            logger()->debug('Photon returned empty result', [
                'query' => $query,
                'lat' => $lat,
                'lng' => $lng,
            ]);
        }

        $suggestions = [];

        foreach ($features as $feature) {
            $props = $feature['properties'] ?? [];
            $coords = $feature['geometry']['coordinates'] ?? null;

            if (! is_array($coords) || count($coords) < 2) {
                continue;
            }

            $lon = (float) $coords[0];
            $latValue = (float) $coords[1];

            if (! is_finite($latValue) || ! is_finite($lon)) {
                continue;
            }

            $street = $props['street'] ?? $props['name'] ?? null;
            $city = $props['city'] ?? null;
            $label = implode(', ', array_filter([$street, $city]));

            $suggestions[] = [
                'label' => $label !== '' ? $label : ($props['name'] ?? ''),
                'name' => $props['name'] ?? null,
                'street' => $props['street'] ?? null,
                'city' => $props['city'] ?? null,
                'state' => $props['state'] ?? null,
                'country' => $props['country'] ?? null,
                'lat' => $latValue,
                'lng' => $lon,
            ];
        }

        usort($suggestions, function ($a, $b) use ($query, $lat, $lng) {
            $aScore = similar_text(mb_strtolower((string) ($a['label'] ?? '')), mb_strtolower($query));
            $bScore = similar_text(mb_strtolower((string) ($b['label'] ?? '')), mb_strtolower($query));

            if ($aScore !== $bScore) {
                return $bScore <=> $aScore;
            }

            if ($lat !== null && $lng !== null) {
                $da = sqrt(pow(((float) ($a['lat'] ?? 0)) - $lat, 2) + pow(((float) ($a['lng'] ?? 0)) - $lng, 2));
                $db = sqrt(pow(((float) ($b['lat'] ?? 0)) - $lat, 2) + pow(((float) ($b['lng'] ?? 0)) - $lng, 2));

                return $da <=> $db;
            }

            return 0;
        });

        $suggestions = array_slice($suggestions, 0, 10);

        return $suggestions;
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
