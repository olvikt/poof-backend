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
                    'city' => 'Дніпро',
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
                        ],
                        'geometry' => [
                            'coordinates' => [30.5241361, 50.4500336],
                        ],
                    ],
                    [
                        'properties' => [
                            'name' => 'Київ',
                            'city' => 'Київ',
                        ],
                        'geometry' => [
                            'coordinates' => [30.5241361, 50.4500336],
                        ],
                    ],
                    [
                        'properties' => [
                            'city' => 'Львів',
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
            return $request->url() === 'https://photon.komoot.io/api/?q=%D0%BD%D0%B0%D0%B1%D0%B5%D1%80%D0%B5%D0%B6%D0%BD%D0%B0&lat=48.42&lon=35.05&limit=15&bbox=22.0%2C44.0%2C40.0%2C53.0&lang=uk';
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

}
