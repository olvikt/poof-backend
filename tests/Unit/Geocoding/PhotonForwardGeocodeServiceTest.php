<?php

namespace Tests\Unit\Geocoding;

use App\Services\Geocoding\GeocodeTextNormalizer;
use App\Services\Geocoding\PhotonForwardGeocodeService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PhotonForwardGeocodeServiceTest extends TestCase
{
    private function service(): PhotonForwardGeocodeService
    {
        return new PhotonForwardGeocodeService(new GeocodeTextNormalizer());
    }

    public function test_it_normalizes_candidate_payload_and_filters_sparse_or_malformed_features(): void
    {
        Http::fake([
            'https://photon.komoot.io/api/*' => Http::response([
                'features' => [
                    [
                        'properties' => [
                            'street' => 'Мандриківська',
                            'housenumber' => '23',
                            'city' => 'Дніпро',
                            'state' => 'Дніпропетровська область',
                            'countrycode' => 'UA',
                        ],
                        'geometry' => ['coordinates' => [35.0308, 48.4572]],
                    ],
                    [
                        'properties' => [
                            'name' => 'Львів',
                            'city' => 'Львів',
                            'countrycode' => 'UA',
                        ],
                        'geometry' => ['coordinates' => [24.0315921, 49.842957]],
                    ],
                    [
                        'properties' => ['name' => 'Broken coord'],
                        'geometry' => ['coordinates' => [30.5]],
                    ],
                    [
                        'properties' => ['name' => 'No geometry'],
                    ],
                ],
            ]),
        ]);

        $query = (new GeocodeTextNormalizer())->buildSearchQueryProfile('мандриківська');
        $result = $this->service()->fetchSuggestions($query, null, null);

        $this->assertCount(2, $result);
        $this->assertSame('Мандриківська 23, Дніпро', $result[0]['label']);
        $this->assertSame('Мандриківська', $result[0]['street']);
        $this->assertSame('23', $result[0]['house']);
        $this->assertSame('Львів, Львів', $result[1]['label']);
    }

    public function test_it_prioritizes_house_number_match_over_street_only_noise_with_location_context(): void
    {
        Http::fake([
            'https://photon.komoot.io/api/*' => Http::response([
                'features' => [
                    [
                        'properties' => [
                            'street' => 'Мандриківська вулиця',
                            'housenumber' => '173',
                            'city' => 'Дніпро',
                            'state' => 'Дніпропетровська область',
                            'countrycode' => 'UA',
                        ],
                        'geometry' => ['coordinates' => [35.0462, 48.4647]],
                    ],
                    [
                        'properties' => [
                            'street' => 'Мандриківська вулиця',
                            'city' => 'Кривий Ріг',
                            'state' => 'Дніпропетровська область',
                            'countrycode' => 'UA',
                        ],
                        'geometry' => ['coordinates' => [33.3918, 47.9105]],
                    ],
                ],
            ]),
        ]);

        $query = (new GeocodeTextNormalizer())->buildSearchQueryProfile('мандриківська 173');
        $result = $this->service()->fetchSuggestions($query, 48.4662, 35.0500);

        $this->assertNotEmpty($result);
        $this->assertSame('173', $result[0]['house']);
        $this->assertSame('Дніпро', $result[0]['city']);
    }

    public function test_it_applies_locality_bias_for_short_prefix_query(): void
    {
        Http::fake([
            'https://photon.komoot.io/api/*' => Http::response([
                'features' => [
                    [
                        'properties' => [
                            'street' => 'Центральна',
                            'city' => 'Дніпро',
                            'state' => 'Дніпропетровська область',
                            'countrycode' => 'UA',
                        ],
                        'geometry' => ['coordinates' => [35.0462, 48.4647]],
                    ],
                    [
                        'properties' => [
                            'street' => 'Центральна',
                            'city' => 'Київ',
                            'state' => 'Київська область',
                            'countrycode' => 'UA',
                        ],
                        'geometry' => ['coordinates' => [30.5241361, 50.4500336]],
                    ],
                ],
            ]),
        ]);

        $query = (new GeocodeTextNormalizer())->buildSearchQueryProfile('цент');
        $result = $this->service()->fetchSuggestions($query, 48.4662, 35.05);

        $this->assertNotEmpty($result);
        $this->assertSame('Дніпро', $result[0]['city']);
    }

    public function test_it_filters_foreign_and_ru_orthography_noise_when_strong_ukrainian_candidate_exists(): void
    {
        Http::fake([
            'https://photon.komoot.io/api/*' => Http::response([
                'features' => [
                    [
                        'properties' => [
                            'street' => 'Січеславська Набережна',
                            'city' => 'Дніпро',
                            'state' => 'Дніпропетровська область',
                            'countrycode' => 'UA',
                        ],
                        'geometry' => ['coordinates' => [35.05, 48.47]],
                    ],
                    [
                        'properties' => [
                            'street' => 'Сичеславская Набережная',
                            'city' => 'Днепр',
                            'state' => 'Днепропетровская область',
                            'countrycode' => 'UA',
                        ],
                        'geometry' => ['coordinates' => [35.2, 48.55]],
                    ],
                    [
                        'properties' => [
                            'street' => 'Centralna',
                            'city' => 'Warszawa',
                            'countrycode' => 'PL',
                        ],
                        'geometry' => ['coordinates' => [21.0122, 52.2297]],
                    ],
                ],
            ]),
        ]);

        $query = (new GeocodeTextNormalizer())->buildSearchQueryProfile('січеславська');
        $result = $this->service()->fetchSuggestions($query, null, null);

        $this->assertCount(1, $result);
        $this->assertSame('Січеславська Набережна, Дніпро', $result[0]['label']);
    }

    public function test_it_deduplicates_results_and_applies_limit_of_ten_items(): void
    {
        $features = [];
        for ($i = 1; $i <= 12; $i++) {
            $features[] = [
                'properties' => [
                    'street' => 'Вулиця Тестова',
                    'housenumber' => (string) $i,
                    'city' => 'Київ',
                    'state' => 'Київська область',
                    'countrycode' => 'UA',
                ],
                'geometry' => ['coordinates' => [30.52 + ($i * 0.001), 50.45 + ($i * 0.001)]],
            ];
        }

        $features[] = $features[0];

        Http::fake([
            'https://photon.komoot.io/api/*' => Http::response(['features' => $features]),
        ]);

        $query = (new GeocodeTextNormalizer())->buildSearchQueryProfile('тестова');
        $result = $this->service()->fetchSuggestions($query, null, null);

        $this->assertCount(10, $result);
        $this->assertCount(10, array_unique(array_map(fn (array $row): string => $row['label'], $result)));
    }
}
