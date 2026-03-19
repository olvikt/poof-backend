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
    private const PHOTON_CACHE_VERSION = 'v4';

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

        $parsedQuery = $this->parseSearchQuery($query);
        $suggestions = [];

        foreach ($features as $feature) {
            $props = $feature['properties'] ?? [];
            $coords = $feature['geometry']['coordinates'] ?? null;

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

            $distance = null;

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

            $suggestion = [
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

            $suggestion['_distance_km'] = $distance;
            $suggestion['_rank_score'] = $this->scoreSuggestion($suggestion, $parsedQuery, $distance);

            $suggestions[] = $suggestion;
        }

        $suggestions = array_values(array_filter($suggestions, function (array $suggestion) use ($parsedQuery, $lat, $lng) {
            $distance = $suggestion['_distance_km'] ?? null;
            $score = (float) ($suggestion['_rank_score'] ?? 0.0);
            $hasHouse = $this->nullableString($suggestion['house'] ?? $suggestion['housenumber'] ?? null) !== null;

            if ($lat !== null && $lng !== null && $distance !== null && $parsedQuery['house'] !== null) {
                if ($distance > 250 && $score < 10 && ! $hasHouse) {
                    return false;
                }

                if ($distance > 75 && ! $hasHouse && $score < 35) {
                    return false;
                }
            }

            return true;
        }));

        usort($suggestions, function ($a, $b) {
            $aScore = (float) ($a['_rank_score'] ?? 0.0);
            $bScore = (float) ($b['_rank_score'] ?? 0.0);

            if ($aScore !== $bScore) {
                return $bScore <=> $aScore;
            }

            $aDistance = $a['_distance_km'] ?? INF;
            $bDistance = $b['_distance_km'] ?? INF;

            if ($aDistance !== $bDistance) {
                return $aDistance <=> $bDistance;
            }

            return strcmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
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
            unset($suggestion['_distance_km'], $suggestion['_rank_score']);
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

    private function parseSearchQuery(string $query): array
    {
        $normalized = $this->normalizeSearchText($query);
        preg_match('/(?<!\pL)(\d{1,5}[\pL\/-]?)(?!\pL)/u', $normalized, $houseMatches);
        $house = isset($houseMatches[1]) ? trim($houseMatches[1]) : null;
        $tokens = $this->tokenizeSearchText($normalized);
        $streetTokens = array_values(array_filter($tokens, function (string $token) use ($house) {
            return $house === null || $token !== $house;
        }));

        return [
            'normalized' => $normalized,
            'tokens' => $tokens,
            'street_tokens' => $streetTokens,
            'house' => $house,
            'has_house_intent' => $house !== null,
        ];
    }

    private function scoreSuggestion(array $suggestion, array $parsedQuery, ?float $distanceKm): float
    {
        $label = $this->normalizeSearchText((string) ($suggestion['label'] ?? ''));
        $street = $this->normalizeSearchText((string) ($suggestion['street'] ?? $suggestion['name'] ?? ''));
        $city = $this->normalizeSearchText((string) ($suggestion['city'] ?? ''));
        $region = $this->normalizeSearchText((string) ($suggestion['region'] ?? $suggestion['state'] ?? ''));
        $house = $this->normalizeSearchText((string) ($suggestion['house'] ?? $suggestion['housenumber'] ?? ''));
        $candidateTokens = array_unique(array_merge(
            $this->tokenizeSearchText($label),
            $this->tokenizeSearchText($street),
            $this->tokenizeSearchText($city),
            $this->tokenizeSearchText($region)
        ));

        $streetQuery = implode(' ', $parsedQuery['street_tokens']);
        $streetOverlap = $this->tokenOverlapRatio($parsedQuery['street_tokens'], $this->tokenizeSearchText($street));
        $overallOverlap = $this->tokenOverlapRatio($parsedQuery['tokens'], $candidateTokens);
        $cityOverlap = $this->tokenOverlapRatio($parsedQuery['tokens'], $this->tokenizeSearchText($city));
        $regionOverlap = $this->tokenOverlapRatio($parsedQuery['tokens'], $this->tokenizeSearchText($region));
        $hasHouseIntent = (bool) ($parsedQuery['has_house_intent'] ?? false);
        $hasHouse = $house !== '';

        similar_text($label, $parsedQuery['normalized'], $labelSimilarity);
        similar_text($street, $streetQuery, $streetSimilarity);

        $score = 0.0;
        $score += $streetOverlap * 46;
        $score += $overallOverlap * 20;
        $score += $streetSimilarity * 0.3;
        $score += $labelSimilarity * 0.1;

        if ($street !== '' && $streetQuery !== '' && str_contains($street, $streetQuery)) {
            $score += 18;
        }

        if ($hasHouse) {
            $score += 8;
        }

        if ($hasHouseIntent) {
            if ($hasHouse) {
                if ($house === $parsedQuery['house']) {
                    $score += 55;
                } elseif (str_starts_with($house, $parsedQuery['house']) || str_starts_with($parsedQuery['house'], $house)) {
                    $score += 26;
                } else {
                    $score += 10;
                }
            } else {
                $score -= 26;

                if ($streetOverlap >= 0.75) {
                    $score -= 10;
                }
            }

            if ($distanceKm !== null && $distanceKm > 15) {
                $score -= min($hasHouse ? 28 : 42, 8 + ($distanceKm / ($hasHouse ? 10 : 7)));
            }

            if (! $hasHouse && $distanceKm !== null && $distanceKm > 40) {
                $score -= min(32, 10 + ($distanceKm / 12));
            }
        }

        if ($city !== '' && $cityOverlap > 0) {
            $score += $cityOverlap * 12;
        }

        if ($region !== '' && $regionOverlap > 0) {
            $score += $regionOverlap * 6;
        }

        if ($distanceKm !== null) {
            if ($distanceKm <= 2) {
                $score += 24;
            } elseif ($distanceKm <= 10) {
                $score += 18;
            } elseif ($distanceKm <= 25) {
                $score += 12;
            } elseif ($distanceKm <= 75) {
                $score += 4;
            } elseif ($distanceKm <= 150) {
                $score -= 8;
            } elseif ($distanceKm <= 300) {
                $score -= 22;
            } else {
                $score -= 36;
            }
        }

        if ($overallOverlap < 0.45) {
            $score -= $hasHouseIntent ? 24 : 18;
        }

        if ($streetOverlap < 0.5) {
            $score -= $hasHouseIntent ? 18 : 12;
        }

        return $score;
    }

    private function normalizeSearchText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[,.]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function tokenizeSearchText(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(preg_split('/[^\pL\pN\/-]+/u', $value) ?: [], function (string $token) {
            return $token !== '';
        }));
    }

    private function tokenOverlapRatio(array $needles, array $haystack): float
    {
        $needles = array_values(array_unique(array_filter($needles)));
        $haystack = array_values(array_unique(array_filter($haystack)));

        if ($needles === [] || $haystack === []) {
            return 0.0;
        }

        $matches = 0;

        foreach ($needles as $needle) {
            foreach ($haystack as $token) {
                if ($token === $needle || str_contains($token, $needle) || str_contains($needle, $token)) {
                    $matches++;
                    break;
                }
            }
        }

        return $matches / count($needles);
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
