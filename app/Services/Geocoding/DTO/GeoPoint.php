<?php

namespace App\Services\Geocoding\DTO;

class GeoPoint
{
    public function __construct(
        public float  $lat,
        public float  $lng,
        public string $address,
        public ?string $placeId = null,
        public ?string $accuracy = null,
    ) {}
}
