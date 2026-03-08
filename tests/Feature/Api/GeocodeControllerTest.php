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

    public function test_it_does_not_cache_repeated_queries_while_debugging(): void
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

        Http::assertSentCount(2);
    }

    public function test_it_does_not_cache_empty_results(): void
    {
        Http::fake([
            'https://photon.komoot.io/api*' => Http::response([
                'features' => [],
            ]),
        ]);

        $this->getJson('/api/geocode?q=порожньо')->assertOk()->assertExactJson([]);
        $this->getJson('/api/geocode?q=порожньо')->assertOk()->assertExactJson([]);

        Http::assertSentCount(2);
    }

}
