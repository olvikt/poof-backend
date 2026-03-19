<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocodeControllerTest extends TestCase
{
    public function test_it_returns_empty_payload_for_too_short_query(): void
    {
        Http::fake();

        $response = $this->getJson('/api/geocode?q=a');

        $response->assertOk()->assertExactJson([]);
        Http::assertNothingSent();
    }

    public function test_it_normalizes_photon_response(): void
    {
        Http::fake([
            'https://photon.komoot.io/api*' => Http::response([
                'features' => [
                    [
                        'properties' => [
                            'street' => 'Мандриківська',
                            'housenumber' => '23',
                            'city' => 'Дніпро',
                            'state' => 'Дніпропетровська область',
                            'countrycode' => 'UA',
                        ],
                        'geometry' => [
                            'coordinates' => [35.0308, 48.4572],
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this->getJson('/api/geocode?q=мандрик');

        $response
            ->assertOk()
            ->assertJson([
                [
                    'label' => 'Мандриківська 23',
                    'street' => 'Мандриківська',
                    'house' => '23',
                    'city' => 'Дніпро',
                    'region' => 'Дніпропетровська область',
                    'line1' => 'Мандриківська 23',
                    'line2' => 'Дніпро, Дніпропетровська область',
                    'lat' => 48.4572,
                    'lng' => 35.0308,
                ],
            ]);
    }


    public function test_it_handles_sparse_features_and_limits_unique_results(): void
    {
        Http::fake([
            'https://photon.komoot.io/api*' => Http::response([
                'features' => [
                    [
                        'properties' => [
                            'name' => 'Київ',
                            'city' => 'Київ',
                            'countrycode' => 'UA',
                        ],
                        'geometry' => [
                            'coordinates' => [30.5241361, 50.4500336],
                        ],
                    ],
                    [
                        'properties' => [
                            'name' => 'Київ',
                            'city' => 'Київ',
                            'countrycode' => 'UA',
                        ],
                        'geometry' => [
                            'coordinates' => [30.5241361, 50.4500336],
                        ],
                    ],
                    [
                        'properties' => [
                            'city' => 'Львів',
                            'countrycode' => 'UA',
                        ],
                        'geometry' => [
                            'coordinates' => [24.0315921, 49.842957],
                        ],
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/geocode?q=київ')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.label', 'Київ')
            ->assertJsonPath('0.street', 'Київ')
            ->assertJsonPath('0.city', 'Київ')
            ->assertJsonPath('0.lat', 50.4500336)
            ->assertJsonPath('0.lng', 30.5241361)
            ->assertJsonPath('1.label', 'Львів')
            ->assertJsonPath('1.street', null)
            ->assertJsonPath('1.city', 'Львів');
    }

    public function test_it_caches_repeated_queries(): void
    {
        Http::fake([
            'https://photon.komoot.io/api*' => Http::response([
                'features' => [
                    [
                        'properties' => [
                            'name' => 'Січових Стрільців',
                            'city' => 'Київ',
                            'countrycode' => 'UA',
                        ],
                        'geometry' => [
                            'coordinates' => [30.52, 50.45],
                        ],
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/geocode?q=стрільців')->assertOk();
        $this->getJson('/api/geocode?q=стрільців')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_it_caches_empty_results(): void
    {
        Http::fake([
            'https://photon.komoot.io/api*' => Http::response([
                'features' => [],
            ]),
        ]);

        $this->getJson('/api/geocode?q=порожньо')->assertOk()->assertExactJson([]);
        $this->getJson('/api/geocode?q=порожньо')->assertOk()->assertExactJson([]);

        Http::assertSentCount(1);
    }

    public function test_it_uses_photon_supported_query_params(): void
    {
        Http::fake([
            'https://photon.komoot.io/api*' => Http::response([
                'features' => [],
            ]),
        ]);

        $this->getJson('/api/geocode?q=набережна&lat=48.42&lng=35.05')->assertOk();

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->method() === 'GET'
                && ($data['q'] ?? null) === 'набережна'
                && ($data['lat'] ?? null) === '48.42'
                && ($data['lon'] ?? null) === '35.05'
                && ($data['limit'] ?? null) === '15'
                && ! array_key_exists('lang', $data)
                && ($data['layer'] ?? null) === 'street'
                && ($data['bbox'] ?? null) === '22.0,44.0,40.0,53.0'
                && ! array_key_exists('countrycode', $data);
        });
    }

    public function test_it_keeps_results_within_broad_distance_radius(): void
    {
        Http::fake([
            'https://photon.komoot.io/api*' => Http::response([
                'features' => [
                    [
                        'properties' => [
                            'name' => 'Набережна',
                            'city' => 'Дніпро',
                            'countrycode' => 'UA',
                        ],
                        'geometry' => [
                            'coordinates' => [27.56, 53.9],
                        ],
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/geocode?q=набережна&lat=48.42&lng=35.05')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.label', 'Набережна, Дніпро');
    }

    public function test_it_filters_non_ukrainian_results_without_using_unsupported_countrycode_param(): void
    {
        Http::fake([
            'https://photon.komoot.io/api*' => Http::response([
                'features' => [
                    [
                        'properties' => [
                            'name' => 'Centralna',
                            'city' => 'Warszawa',
                            'countrycode' => 'PL',
                        ],
                        'geometry' => [
                            'coordinates' => [21.0122, 52.2297],
                        ],
                    ],
                    [
                        'properties' => [
                            'name' => 'Центральна',
                            'city' => 'Дніпро',
                            'countrycode' => 'UA',
                        ],
                        'geometry' => [
                            'coordinates' => [35.0462, 48.4647],
                        ],
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/geocode?q=центральна')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.label', 'Центральна, Дніпро')
            ->assertJsonPath('0.city', 'Дніпро');
    }


    public function test_house_number_query_prefers_nearby_matching_street_over_distant_street_only_noise(): void
    {
        Http::fake([
            'https://photon.komoot.io/api*' => Http::response([
                'features' => [
                    [
                        'properties' => [
                            'street' => 'Мандриківська вулиця',
                            'housenumber' => '173',
                            'city' => 'Дніпро',
                            'state' => 'Дніпропетровська область',
                            'countrycode' => 'UA',
                        ],
                        'geometry' => [
                            'coordinates' => [35.0462, 48.4647],
                        ],
                    ],
                    [
                        'properties' => [
                            'street' => 'Мандриківська вулиця',
                            'city' => 'Кривий Ріг',
                            'state' => 'Дніпропетровська область',
                            'countrycode' => 'UA',
                        ],
                        'geometry' => [
                            'coordinates' => [33.3918, 47.9105],
                        ],
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/geocode?q=мандриківська вулиця 173&lat=48.4647&lng=35.0462')
            ->assertOk()
            ->assertJsonPath('0.label', 'Мандриківська вулиця 173, Дніпро')
            ->assertJsonPath('1.label', 'Мандриківська вулиця, Кривий Ріг');
    }

    public function test_house_number_match_ranks_above_candidate_without_house_match(): void
    {
        Http::fake([
            'https://photon.komoot.io/api*' => Http::response([
                'features' => [
                    [
                        'properties' => [
                            'street' => 'Землеробський провулок',
                            'city' => 'Дніпро',
                            'state' => 'Дніпропетровська область',
                            'countrycode' => 'UA',
                        ],
                        'geometry' => [
                            'coordinates' => [35.047, 48.465],
                        ],
                    ],
                    [
                        'properties' => [
                            'street' => 'Землеробський провулок',
                            'housenumber' => '25',
                            'city' => 'Дніпро',
                            'state' => 'Дніпропетровська область',
                            'countrycode' => 'UA',
                        ],
                        'geometry' => [
                            'coordinates' => [35.0469, 48.4649],
                        ],
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/geocode?q=землеробський провулок 25&lat=48.4647&lng=35.0462')
            ->assertOk()
            ->assertJsonPath('0.house', '25')
            ->assertJsonPath('1.house', null);
    }

    public function test_distant_noisy_results_remain_but_rank_below_relevant_matches_without_bias(): void
    {
        Http::fake([
            'https://photon.komoot.io/api*' => Http::response([
                'features' => [
                    [
                        'properties' => [
                            'street' => 'Мандриківська вулиця',
                            'city' => 'Кривий Ріг',
                            'state' => 'Дніпропетровська область',
                            'countrycode' => 'UA',
                        ],
                        'geometry' => [
                            'coordinates' => [33.3918, 47.9105],
                        ],
                    ],
                    [
                        'properties' => [
                            'street' => 'Мандриківська вулиця',
                            'housenumber' => '173',
                            'city' => 'Дніпро',
                            'state' => 'Дніпропетровська область',
                            'countrycode' => 'UA',
                        ],
                        'geometry' => [
                            'coordinates' => [35.0462, 48.4647],
                        ],
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/geocode?q=мандриківська вулиця 173')
            ->assertOk()
            ->assertJsonPath('0.city', 'Дніпро')
            ->assertJsonPath('0.house', '173')
            ->assertJsonPath('1.city', 'Кривий Ріг')
            ->assertJsonPath('1.house', null);
    }

    public function test_it_keeps_reverse_geocode_path_working(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'display_name' => 'Тестова 7, Дніпро, Дніпропетровська область, Україна',
                'address' => [
                    'road' => 'Тестова',
                    'house_number' => '7',
                    'city' => 'Дніпро',
                    'state' => 'Дніпропетровська область',
                ],
            ]),
        ]);

        $this->getJson('/api/geocode?lat=48.4647&lng=35.0462')
            ->assertOk()
            ->assertJson([
                [
                    'label' => 'Тестова 7',
                    'street' => 'Тестова',
                    'house' => '7',
                    'city' => 'Дніпро',
                    'region' => 'Дніпропетровська область',
                    'line1' => 'Тестова 7',
                    'line2' => 'Дніпро, Дніпропетровська область',
                    'lat' => 48.4647,
                    'lng' => 35.0462,
                ],
            ]);
    }

    public function test_it_degrades_safely_when_photon_upstream_fails(): void
    {
        Http::fake([
            'https://photon.komoot.io/api*' => Http::response([
                'message' => 'Language is not supported. Supported are: default, de, en, fr',
            ], 400),
        ]);

        $this->getJson('/api/geocode?q=центральна')
            ->assertOk()
            ->assertExactJson([]);
    }

}
