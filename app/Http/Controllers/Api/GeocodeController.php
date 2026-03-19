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
    private const PHOTON_CACHE_VERSION = 'v3';

    private const UKRAINE_BBOX = '22.0,44.0,40.0,53.0';

    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $normalizedQuery = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $query) ?? ''));
        $lat = $this->normalizedCoordinate($request->query('lat'), -90, 90);
        $lng = $this->normalizedCoordinate(
            $request->query('lng', $request->query('lon')),
            -180,
            180
        );

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

        $cacheKey = 'geocode:photon:' . self::PHOTON_CACHE_VERSION . ':' . md5($normalizedQuery . '|' . ($lat ?? 'null') . '|' . ($lng ?? 'null'));

        try {
            $suggestions = Cache::get($cacheKey);

            if (! is_array($suggestions)) {
                \Log::debug('Photon cache miss', [
                    'query' => $normalizedQuery,
                    'lat' => $lat,
                    'lng' => $lng,
                ]);

                $suggestions = $this->fetchPhotonSuggestions($normalizedQuery, $lat, $lng);

                Cache::put($cacheKey, $suggestions, now()->addMinutes(30));
            }
        } catch (\Throwable $e) {
            logger()->error('Photon request failed', [
                'query' => $normalizedQuery,
                'error' => $e->getMessage(),
            ]);

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
        $params = [
            'q' => $query,
            'limit' => 15,
            'layer' => 'street',
            'bbox' => self::UKRAINE_BBOX,
        ];

        if ($lat !== null && $lng !== null) {
            $params['lat'] = $lat;
            $params['lon'] = $lng;
        }

        try {
            $response = Http::timeout(8)
                ->retry(1, 100)
                ->acceptJson()
                ->get('https://photon.komoot.io/api/', $params);
        } catch (\Throwable $e) {
            logger()->error('Photon request failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        if (! $response->successful()) {
            logger()->error('Photon request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'query' => $query,
            ]);

            return [];
        }

        $data = $response->json();

        logger()->debug('Photon features', [
            'query' => $query,
            'features' => count($data['features'] ?? []),
        ]);

        logger()->debug('PHOTON RAW', [
            'query' => $query,
            'features' => $data['features'] ?? [],
        ]);

        $features = collect($data['features'] ?? [])
            ->values()
            ->all();

        logger()->debug('Photon results', [
            'query' => $query,
            'features' => count($features),
        ]);

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

            // Photon `properties.type` can vary (e.g. `street`, `residential`) and
            // should not be used as a hard filter for autocomplete suggestions.
            if (! is_array($coords) || count($coords) < 2) {
                continue;
            }

            $featureLon = (float) $coords[0];
            $featureLat = (float) $coords[1];

            if (! is_finite($featureLat) || ! is_finite($featureLon)) {
                continue;
            }

            if (! $this->isWithinUkraineScope($props, $featureLat, $featureLon)) {
                continue;
            }

            if ($lat !== null && $lng !== null) {
                $distance = $this->distance($lat, $lng, $featureLat, $featureLon);

                if ($distance > 1000) {
                    continue;
                }
            }

            $street = $props['street']
                ?? $props['name']
                ?? $props['road']
                ?? null;

            if (! $street) {
                $street = $props['name'] ?? null;
            }
            $house = $props['housenumber'] ?? $props['house_number'] ?? null;
            $city = $props['city']
                ?? $props['district']
                ?? $props['county']
                ?? $props['state']
                ?? null;
            $region = $props['state'] ?? $props['region'] ?? null;
            $line1 = trim(implode(' ', array_filter([$street, $house])));
            $line2 = trim(implode(', ', array_filter([$city, $region])));
            $label = trim(implode(', ', array_filter([
                $line1,
                $city,
            ])));

            if ($label === '') {
                $label = $street ?? $props['name'] ?? 'Unknown address';
            }

            $suggestions[] = [
                'label' => $label,
                'name' => $street,
                'street' => $street,
                'house' => $house,
                'housenumber' => $house,
                'city' => $city,
                'region' => $region,
                'line1' => $line1 !== '' ? $line1 : null,
                'line2' => $line2 !== '' ? $line2 : null,
                'state' => $props['state'] ?? null,
                'country' => $props['country'] ?? null,
                'lat' => $featureLat,
                'lng' => $featureLon,
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

        $unique = [];
        $seen = [];

        foreach ($suggestions as $suggestion) {
            $key = mb_strtolower(trim(implode('-', array_filter([
                (string) ($suggestion['street'] ?? ''),
                (string) ($suggestion['house'] ?? $suggestion['housenumber'] ?? ''),
                (string) ($suggestion['city'] ?? ''),
            ]))));

            if ($key === '') {
                $key = mb_strtolower((string) ($suggestion['label'] ?? ''));
            }

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $suggestion;

            if (count($unique) >= 10) {
                break;
            }
        }

        logger()->debug('Photon filtered results', [
            'count' => count($unique),
        ]);

        return $unique;
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

    private function isWithinUkraineScope(array $properties, float $lat, float $lng): bool
    {
        $countryCode = mb_strtolower(trim((string) ($properties['countrycode'] ?? '')));

        if ($countryCode !== '') {
            return $countryCode === 'ua';
        }

        [$minLng, $minLat, $maxLng, $maxLat] = array_map('floatval', explode(',', self::UKRAINE_BBOX));

        return $lat >= $minLat && $lat <= $maxLat && $lng >= $minLng && $lng <= $maxLng;
    }

    private function distance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earth * $c;
    }
}
