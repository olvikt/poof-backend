<?php

namespace App\Services\Geocoding;

use App\Services\Geocoding\Contracts\GeocoderInterface;

class Geocoder
{
    public function __construct(
        protected GeocoderInterface $provider
    ) {}

    public function autocomplete(string $query): array
    {
        return $this->provider->autocomplete($query);
    }

    public function place(string $placeId)
    {
        return $this->provider->placeDetails($placeId);
    }

    public function reverse(float $lat, float $lng)
    {
        return $this->provider->reverse($lat, $lng);
    }
}
