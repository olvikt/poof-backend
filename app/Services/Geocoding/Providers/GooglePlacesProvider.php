<?php

namespace App\Services\Geocoding\Providers;

use App\Services\Geocoding\Contracts\GeocoderInterface;
use App\Services\Geocoding\DTO\GeoPoint;
use Illuminate\Support\Facades\Http;

class GooglePlacesProvider implements GeocoderInterface
{
    protected string $key;
    protected string $language;
    protected string $region;

    public function __construct()
    {
        $this->key = config('geocoding.google.key');
        $this->language = config('geocoding.google.language', 'uk');
        $this->region = config('geocoding.google.region', 'ua');
    }

    public function autocomplete(string $query): array
    {
        $response = Http::get(
            'https://maps.googleapis.com/maps/api/place/autocomplete/json',
            [
                'input'     => $query,
                'key'       => $this->key,
                'language'  => $this->language,
                'region'    => $this->region,
                'types'     => 'address',
            ]
        )->json();

        return collect($response['predictions'] ?? [])
            ->map(fn ($item) => [
                'place_id' => $item['place_id'],
                'label'    => $item['description'],
            ])
            ->all();
    }

    public function placeDetails(string $placeId): GeoPoint
    {
        $response = Http::get(
            'https://maps.googleapis.com/maps/api/place/details/json',
            [
                'place_id' => $placeId,
                'key'      => $this->key,
                'language' => $this->language,
            ]
        )->json();

        $result = $response['result'];

        return new GeoPoint(
            lat: $result['geometry']['location']['lat'],
            lng: $result['geometry']['location']['lng'],
            address: $result['formatted_address'],
            placeId: $placeId,
            accuracy: 'rooftop',
        );
    }

    public function reverse(float $lat, float $lng): GeoPoint
    {
        $response = Http::get(
            'https://maps.googleapis.com/maps/api/geocode/json',
            [
                'latlng'  => "{$lat},{$lng}",
                'key'     => $this->key,
                'language'=> $this->language,
            ]
        )->json();

        $result = $response['results'][0] ?? null;

        return new GeoPoint(
            lat: $lat,
            lng: $lng,
            address: $result['formatted_address'] ?? 'Unknown location',
            placeId: $result['place_id'] ?? null,
            accuracy: 'rooftop',
        );
    }
}
