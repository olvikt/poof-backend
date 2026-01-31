<?php

namespace App\Services\Geocoding\Contracts;

use App\Services\Geocoding\DTO\GeoPoint;

interface GeocoderInterface
{
    /**
     * Autocomplete адресов
     */
    public function autocomplete(string $query): array;

    /**
     * Получить координаты и адрес по place_id
     */
    public function placeDetails(string $placeId): GeoPoint;

    /**
     * Reverse-geocoding по координатам
     */
    public function reverse(float $lat, float $lng): GeoPoint;
}
