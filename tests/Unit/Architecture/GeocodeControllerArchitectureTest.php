<?php

namespace Tests\Unit\Architecture;

use App\Http\Controllers\Api\GeocodeController;
use ReflectionClass;
use Tests\TestCase;

class GeocodeControllerArchitectureTest extends TestCase
{
    private function normalizedControllerSource(): string
    {
        $contents = file_get_contents(app_path('Http/Controllers/Api/GeocodeController.php'));

        $this->assertIsString($contents);

        return preg_replace('/\s+/', ' ', $contents) ?? '';
    }

    public function test_controller_delegates_geocoding_pipeline_to_services(): void
    {
        $source = $this->normalizedControllerSource();

        $this->assertStringContainsString('private readonly GeocodeTextNormalizer $textNormalizer', $source);
        $this->assertStringContainsString('private readonly PhotonForwardGeocodeService $photonForwardGeocodeService', $source);
        $this->assertStringContainsString('private readonly NominatimReverseGeocodeService $nominatimReverseGeocodeService', $source);

        $this->assertStringContainsString('$this->textNormalizer->buildSearchQueryProfile($normalizedQuery)', $source);
        $this->assertStringContainsString('$this->photonForwardGeocodeService->fetchSuggestions($queryProfile, $lat, $lng)', $source);
        $this->assertStringContainsString('$this->nominatimReverseGeocodeService->fetchSuggestions($lat, $lng)', $source);
    }

    public function test_controller_does_not_reintroduce_heavy_geocode_logic_or_direct_payload_parsing(): void
    {
        $source = $this->normalizedControllerSource();

        $forbiddenPatterns = [
            'scoreSuggestion(',
            'filterForeignNoiseSuggestions(',
            'resolveLocalBiasContext(',
            'shortPrefixLocalityAdjustment(',
            'isShortAmbiguousPrefixQuery(',
            'tokenizeSearchText(',
            'parseSearchQueryProfile(',
            'distance(',
            'Http::',
            '->json()',
            "['features']",
            "['properties']",
            "['geometry']",
        ];

        foreach ($forbiddenPatterns as $pattern) {
            $this->assertStringNotContainsString($pattern, $source, "Unexpected pattern in GeocodeController: {$pattern}");
        }
    }

    public function test_controller_keeps_only_coordinate_normalization_as_private_helper(): void
    {
        $reflection = new ReflectionClass(GeocodeController::class);

        $privateMethods = collect($reflection->getMethods())
            ->filter(fn ($method) => $method->isPrivate() && $method->getDeclaringClass()->getName() === GeocodeController::class)
            ->map(fn ($method) => $method->getName())
            ->values()
            ->all();

        $this->assertSame(['normalizedCoordinate'], $privateMethods);
    }
}
