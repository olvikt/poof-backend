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

            $data = $response->json();

            if (! isset($data['features']) || ! is_array($data['features'])) {
                return [];
            }

            return collect($data['features'])
                ->take(5)
                ->map(function ($feature) {
                    $p = $feature['properties'] ?? [];
                    $coords = $feature['geometry']['coordinates'] ?? [null, null];

                    $street = $p['street'] ?? $p['name'] ?? null;
                    $house = $p['housenumber'] ?? null;
                    $city = $p['city'] ?? $p['district'] ?? $p['county'] ?? null;

                    if (! $street && ! $city) {
                        return null;
                    }

                    return [
                        'label' => trim($street . ' ' . $house . ', ' . $city),
                        'street' => $street,
                        'house' => $house,
                        'city' => $city,
                        'lat' => $coords[1] ?? null,
                        'lng' => $coords[0] ?? null,
                    ];
                })
                ->filter()
                ->values()
                ->all();
        });

        return response()->json($suggestions);
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
