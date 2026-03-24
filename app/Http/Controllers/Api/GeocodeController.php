<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Geocoding\GeocodeTextNormalizer;
use App\Services\Geocoding\NominatimReverseGeocodeService;
use App\Services\Geocoding\PhotonForwardGeocodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GeocodeController extends Controller
{
    private const PHOTON_CACHE_VERSION = 'v7';

    public function __construct(
        private readonly GeocodeTextNormalizer $textNormalizer,
        private readonly PhotonForwardGeocodeService $photonForwardGeocodeService,
        private readonly NominatimReverseGeocodeService $nominatimReverseGeocodeService,
    ) {
    }

    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $normalizedQuery = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $query) ?? ''));
        $queryProfile = $this->textNormalizer->buildSearchQueryProfile($normalizedQuery);
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
                    fn () => $this->nominatimReverseGeocodeService->fetchSuggestions($lat, $lng)
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
                $suggestions = $this->photonForwardGeocodeService->fetchSuggestions($queryProfile, $lat, $lng);

                Cache::put($cacheKey, $suggestions, now()->addMinutes(30));
            }
        } catch (\Throwable $e) {
            Log::error('Photon request failed', [
                'query' => $normalizedQuery,
                'error' => $e->getMessage(),
            ]);

            $suggestions = [];
        }

        return response()->json(is_array($suggestions) ? $suggestions : []);
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
