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
    private const PHOTON_CACHE_VERSION = 'v7';

    private const UKRAINE_BBOX = '22.0,44.0,40.0,53.0';

    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $normalizedQuery = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $query) ?? ''));
        $queryProfile = $this->buildSearchQueryProfile($normalizedQuery);
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

        $cacheKey = 'geocode:photon:' . self::PHOTON_CACHE_VERSION . ':' . md5(
            json_encode([
                'query' => $queryProfile['cache_key'],
                'lat' => $lat,
                'lng' => $lng,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        try {
            $suggestions = Cache::get($cacheKey);

            if (! is_array($suggestions)) {
                \Log::debug('Photon cache miss', [
                    'query' => $queryProfile['primary'],
                    'lat' => $lat,
                    'lng' => $lng,
                ]);

                $suggestions = $this->fetchPhotonSuggestions($queryProfile, $lat, $lng);

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

    private function fetchPhotonSuggestions(array $queryProfile, ?float $lat, ?float $lng): array
    {
        $features = collect();

        foreach ($queryProfile['variants'] as $variant) {
            $params = [
                'q' => $variant,
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
                    'query' => $variant,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if (! $response->successful()) {
                logger()->error('Photon request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'query' => $variant,
                ]);

                continue;
            }

            $data = $response->json();

            logger()->debug('Photon features', [
                'query' => $variant,
                'features' => count($data['features'] ?? []),
            ]);

            $features = $features->merge($data['features'] ?? []);
        }

        $features = $features
            ->values()
            ->all();

        logger()->debug('Photon results', [
            'query' => $queryProfile['primary'],
            'features' => count($features),
        ]);

        if (empty($features)) {
            logger()->debug('Photon returned empty result', [
                'query' => $queryProfile['primary'],
                'lat' => $lat,
                'lng' => $lng,
            ]);
        }

        $parsedQuery = $this->parseSearchQueryProfile($queryProfile);
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

            $scoreData = $this->scoreSuggestion($suggestion, $parsedQuery, $distance);
            $suggestion['_distance_km'] = $distance;
            $suggestion['_rank_score'] = $scoreData['score'];
            $suggestion['_street_overlap'] = $scoreData['street_overlap'];
            $suggestion['_overall_overlap'] = $scoreData['overall_overlap'];
            $suggestion['_city_normalized'] = $scoreData['city'];
            $suggestion['_region_normalized'] = $scoreData['region'];

            $suggestions[] = $suggestion;
        }

        if ($lat !== null && $lng !== null && $this->isShortAmbiguousPrefixQuery($parsedQuery)) {
            $localContext = $this->resolveLocalBiasContext($suggestions);

            if ($localContext !== null) {
                foreach ($suggestions as &$suggestion) {
                    $suggestion['_rank_score'] += $this->shortPrefixLocalityAdjustment(
                        $suggestion,
                        $localContext
                    );
                }
                unset($suggestion);
            }
        }

        $suggestions = $this->filterForeignNoiseSuggestions($suggestions, $parsedQuery, $lat, $lng);

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
            unset(
                $suggestion['_distance_km'],
                $suggestion['_rank_score'],
                $suggestion['_street_overlap'],
                $suggestion['_overall_overlap'],
                $suggestion['_city_normalized'],
                $suggestion['_region_normalized']
            );
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

    private function parseSearchQueryProfile(array $queryProfile): array
    {
        $base = $this->parseSearchQuery((string) ($queryProfile['primary'] ?? ''));
        $variants = [];

        foreach ($queryProfile['variants'] ?? [] as $variant) {
            $variant = $this->normalizeSearchText((string) $variant);

            if ($variant === '') {
                continue;
            }

            $variants[] = $this->parseSearchQuery($variant);
        }

        $base['variants'] = $variants;
        $base['contains_ru_address_signals'] = (bool) ($queryProfile['contains_ru_address_signals'] ?? false);

        return $base;
    }

    private function scoreSuggestion(array $suggestion, array $parsedQuery, ?float $distanceKm): array
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

        $queryVariants = $parsedQuery['variants'] ?? [$parsedQuery];
        $streetQuery = implode(' ', $parsedQuery['street_tokens']);
        $streetOverlap = 0.0;
        $overallOverlap = 0.0;
        $cityOverlap = 0.0;
        $regionOverlap = 0.0;
        $labelSimilarity = 0.0;
        $streetSimilarity = 0.0;
        $bestVariant = $parsedQuery;

        foreach ($queryVariants as $variant) {
            $variantStreetQuery = implode(' ', $variant['street_tokens']);
            $variantStreetOverlap = $this->tokenOverlapRatio($variant['street_tokens'], $this->tokenizeSearchText($street));
            $variantOverallOverlap = $this->tokenOverlapRatio($variant['tokens'], $candidateTokens);
            $variantCityOverlap = $this->tokenOverlapRatio($variant['tokens'], $this->tokenizeSearchText($city));
            $variantRegionOverlap = $this->tokenOverlapRatio($variant['tokens'], $this->tokenizeSearchText($region));
            similar_text($label, $variant['normalized'], $variantLabelSimilarity);
            similar_text($street, $variantStreetQuery, $variantStreetSimilarity);

            $variantComposite = ($variantStreetOverlap * 3)
                + ($variantOverallOverlap * 2)
                + ($variantStreetSimilarity * 0.01)
                + ($variantLabelSimilarity * 0.005);

            $bestComposite = ($streetOverlap * 3)
                + ($overallOverlap * 2)
                + ($streetSimilarity * 0.01)
                + ($labelSimilarity * 0.005);

            if ($variantComposite >= $bestComposite) {
                $bestVariant = $variant;
                $streetQuery = $variantStreetQuery;
                $streetOverlap = $variantStreetOverlap;
                $overallOverlap = $variantOverallOverlap;
                $cityOverlap = $variantCityOverlap;
                $regionOverlap = $variantRegionOverlap;
                $labelSimilarity = $variantLabelSimilarity;
                $streetSimilarity = $variantStreetSimilarity;
            }
        }

        $hasHouseIntent = (bool) ($parsedQuery['has_house_intent'] ?? false);
        $hasHouse = $house !== '';

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
                if ($house === $bestVariant['house']) {
                    $score += 55;
                } elseif (
                    $bestVariant['house'] !== null
                    && (str_starts_with($house, $bestVariant['house']) || str_starts_with($bestVariant['house'], $house))
                ) {
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

        if (($parsedQuery['contains_ru_address_signals'] ?? false) && $streetOverlap >= 0.7) {
            $score += 8;
        }

        return [
            'score' => $score,
            'street_overlap' => $streetOverlap,
            'overall_overlap' => $overallOverlap,
            'city' => $city,
            'region' => $region,
        ];
    }

    private function isShortAmbiguousPrefixQuery(array $parsedQuery): bool
    {
        if (($parsedQuery['has_house_intent'] ?? false) || ($parsedQuery['house'] ?? null) !== null) {
            return false;
        }

        $streetTokens = array_values(array_filter($parsedQuery['street_tokens'] ?? []));
        $normalized = (string) ($parsedQuery['normalized'] ?? '');

        if ($streetTokens === [] || count($streetTokens) > 2) {
            return false;
        }

        $maxTokenLength = max(array_map(static fn (string $token): int => mb_strlen($token), $streetTokens));

        return mb_strlen($normalized) <= 12 && $maxTokenLength <= 8;
    }

    private function resolveLocalBiasContext(array $suggestions): ?array
    {
        $candidates = array_values(array_filter($suggestions, function (array $suggestion): bool {
            $distance = $suggestion['_distance_km'] ?? null;
            $streetOverlap = (float) ($suggestion['_street_overlap'] ?? 0.0);
            $overallOverlap = (float) ($suggestion['_overall_overlap'] ?? 0.0);

            return $distance !== null
                && $distance <= 120
                && $streetOverlap >= 0.55
                && $overallOverlap >= 0.45;
        }));

        if ($candidates === []) {
            return null;
        }

        usort($candidates, function (array $a, array $b): int {
            $aDistance = (float) ($a['_distance_km'] ?? INF);
            $bDistance = (float) ($b['_distance_km'] ?? INF);

            if ($aDistance !== $bDistance) {
                return $aDistance <=> $bDistance;
            }

            return (float) ($b['_rank_score'] ?? 0.0) <=> (float) ($a['_rank_score'] ?? 0.0);
        });

        $anchor = $candidates[0];

        return [
            'city' => $this->normalizeSearchText((string) ($anchor['_city_normalized'] ?? '')),
            'region' => $this->normalizeSearchText((string) ($anchor['_region_normalized'] ?? '')),
            'distance_km' => (float) ($anchor['_distance_km'] ?? 0.0),
        ];
    }

    private function shortPrefixLocalityAdjustment(array $suggestion, array $localContext): float
    {
        $distanceKm = isset($suggestion['_distance_km']) ? (float) $suggestion['_distance_km'] : null;

        if ($distanceKm === null) {
            return 0.0;
        }

        $candidateCity = $this->normalizeSearchText((string) ($suggestion['_city_normalized'] ?? ''));
        $candidateRegion = $this->normalizeSearchText((string) ($suggestion['_region_normalized'] ?? ''));
        $localCity = $this->normalizeSearchText((string) ($localContext['city'] ?? ''));
        $localRegion = $this->normalizeSearchText((string) ($localContext['region'] ?? ''));

        $adjustment = 0.0;

        if ($distanceKm <= 3) {
            $adjustment += 18;
        } elseif ($distanceKm <= 12) {
            $adjustment += 12;
        } elseif ($distanceKm <= 35) {
            $adjustment += 6;
        } elseif ($distanceKm > 90) {
            $adjustment -= min(28, 10 + ($distanceKm / 20));
        }

        if ($localCity !== '' && $candidateCity === $localCity) {
            $adjustment += 22;
        } elseif ($localCity !== '' && $candidateCity !== '' && $distanceKm > 40) {
            $adjustment -= 12;
        }

        if ($localRegion !== '' && $candidateRegion === $localRegion) {
            $adjustment += 10;
        } elseif ($localRegion !== '' && $candidateRegion !== '' && $distanceKm > 40) {
            $adjustment -= 20;
        }

        if ($distanceKm > 180 && $candidateRegion !== '' && $localRegion !== '' && $candidateRegion !== $localRegion) {
            $adjustment -= 16;
        }

        return $adjustment;
    }

    private function filterForeignNoiseSuggestions(array $suggestions, array $parsedQuery, ?float $lat, ?float $lng): array
    {
        if ($suggestions === []) {
            return [];
        }

        $hasStrongUkrainianCandidate = collect($suggestions)->contains(function (array $suggestion) use ($parsedQuery, $lat, $lng): bool {
            $score = (float) ($suggestion['_rank_score'] ?? 0.0);
            $streetOverlap = (float) ($suggestion['_street_overlap'] ?? 0.0);
            $overallOverlap = (float) ($suggestion['_overall_overlap'] ?? 0.0);
            $distanceKm = $suggestion['_distance_km'] ?? null;

            if ($this->isRussianOrthographySuggestion($suggestion)) {
                return false;
            }

            if ($score < 35 || $streetOverlap < 0.55 || $overallOverlap < 0.45) {
                return false;
            }

            return $lat === null || $lng === null || $distanceKm === null || $distanceKm <= 250;
        });

        if (! $hasStrongUkrainianCandidate) {
            return $suggestions;
        }

        return array_values(array_filter($suggestions, function (array $suggestion) use ($parsedQuery, $lat, $lng): bool {
            if (! $this->isRussianOrthographySuggestion($suggestion)) {
                return true;
            }

            $score = (float) ($suggestion['_rank_score'] ?? 0.0);
            $streetOverlap = (float) ($suggestion['_street_overlap'] ?? 0.0);
            $overallOverlap = (float) ($suggestion['_overall_overlap'] ?? 0.0);
            $distanceKm = $suggestion['_distance_km'] ?? null;
            $hasHouse = $this->nullableString($suggestion['house'] ?? $suggestion['housenumber'] ?? null) !== null;

            if (($parsedQuery['contains_ru_address_signals'] ?? false)
                && $hasHouse
                && $score >= 80
                && $streetOverlap >= 0.85
                && ($distanceKm === null || $distanceKm <= 25)) {
                return true;
            }

            if ($distanceKm !== null && $distanceKm <= 25 && $score >= 60 && $streetOverlap >= 0.75 && $overallOverlap >= 0.65) {
                return true;
            }

            return false;
        }));
    }

    private function isRussianOrthographySuggestion(array $suggestion): bool
    {
        $text = trim(implode(' ', array_filter([
            $suggestion['label'] ?? null,
            $suggestion['street'] ?? $suggestion['name'] ?? null,
            $suggestion['city'] ?? null,
            $suggestion['region'] ?? $suggestion['state'] ?? null,
            $suggestion['country'] ?? null,
        ], fn ($value) => is_string($value) && trim($value) !== '')));

        return $text !== '' && $this->containsRussianAddressSignals($this->normalizeSearchText($text));
    }

    private function normalizeSearchText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[,.]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function buildSearchQueryProfile(string $query): array
    {
        $primary = $this->normalizeSearchText($query);
        $variants = [$primary];
        $fallback = $this->buildUkrainianAddressFallback($primary);

        if ($fallback !== null && $fallback !== $primary) {
            $variants[] = $fallback;
        }

        return [
            'primary' => $primary,
            'variants' => array_values(array_unique(array_filter($variants))),
            'cache_key' => implode('|', array_values(array_unique(array_filter($variants)))),
            'contains_ru_address_signals' => $this->containsRussianAddressSignals($primary),
        ];
    }

    private function buildUkrainianAddressFallback(string $query): ?string
    {
        if ($query === '' || ! preg_match('/\p{Cyrillic}/u', $query) || ! $this->containsRussianAddressSignals($query)) {
            return null;
        }

        $converted = strtr($query, [
            'ё' => 'йо',
            'ы' => 'и',
            'э' => 'е',
            'ъ' => '',
            'и' => 'і',
        ]);

        $patterns = [
            '/овская\b/u' => 'івська',
            '/евская\b/u' => 'івська',
            '/ская\b/u' => 'ська',
            '/ского\b/u' => 'ського',
            '/ский\b/u' => 'ський',
            '/ческих\b/u' => 'чних',
            '/ческая\b/u' => 'чна',
            '/ческое\b/u' => 'чне',
            '/ческий\b/u' => 'чний',
            '/ческ\b/u' => 'чн',
            '/ическая\b/u' => 'ічна',
            '/ический\b/u' => 'ічний',
            '/ическое\b/u' => 'ічне',
            '/иковская\b/u' => 'иківська',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $converted = preg_replace($pattern, $replacement, $converted) ?? $converted;
        }

        $converted = preg_replace('/\s+/u', ' ', $converted) ?? $converted;
        $converted = trim($converted);

        return $converted !== '' ? $converted : null;
    }

    private function containsRussianAddressSignals(string $query): bool
    {
        if ($query === '') {
            return false;
        }

        return preg_match('/[ыэёъ]/u', $query) === 1
            || preg_match('/(ская|ского|ский|ческ|ическ|иковск|овск|евск)/u', $query) === 1
            || (preg_match('/\p{Cyrillic}/u', $query) === 1 && preg_match('/и/u', $query) === 1 && preg_match('/[іїєґ]/u', $query) !== 1);
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
