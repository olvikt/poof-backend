<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        $cacheKeyPayload = [
            'q' => mb_strtolower($query),
            'lat' => $lat,
            'lng' => $lng,
        ];

        $cacheKey = 'geocode:photon:' . md5(json_encode($cacheKeyPayload));

        $suggestions = Cache::remember($cacheKey, now()->addHour(), function () use ($query, $lat, $lng): array {
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

            $response = Http::timeout(3)
                ->acceptJson()
                ->get('https://photon.komoot.io/api', $params);

            if (! $response->ok()) {
                return [];
            }

            $features = $response->json('features');
            if (! is_array($features)) {
                return [];
            }

            return collect($features)
                ->map(fn ($feature) => $this->normalizeFeature($feature))
                ->filter()
                ->values()
                ->take(5)
                ->all();
        });

        return response()->json($suggestions);
    }

    private function normalizeFeature(mixed $feature): ?array
    {
        if (! is_array($feature)) {
            return null;
        }

        $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
        $coordinates = is_array($feature['geometry']['coordinates'] ?? null) ? $feature['geometry']['coordinates'] : [];

        $lng = isset($coordinates[0]) ? (float) $coordinates[0] : null;
        $lat = isset($coordinates[1]) ? (float) $coordinates[1] : null;

        if ($lat === null || $lng === null) {
            return null;
        }

        $street = $this->filledValue($properties['street'] ?? $properties['name'] ?? null);
        $house = $this->filledValue($properties['housenumber'] ?? null);
        $city = $this->filledValue($properties['city'] ?? $properties['county'] ?? $properties['district'] ?? null);
        $region = $this->filledValue($properties['state'] ?? $properties['region'] ?? null);

        $line1 = trim(implode(' ', array_filter([$street, $house])));
        $line2 = trim(implode(', ', array_filter([$city, $region])));
        $label = trim(implode(', ', array_filter([$line1, $city])));

        if ($line1 === '' && $label === '') {
            return null;
        }

        return [
            'label' => $label !== '' ? $label : $line1,
            'street' => $street,
            'house' => $house,
            'city' => $city,
            'region' => $region,
            'line1' => $line1 !== '' ? $line1 : null,
            'line2' => $line2 !== '' ? $line2 : null,
            'lat' => $lat,
            'lng' => $lng,
        ];
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

    private function filledValue(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
