<?php

namespace App\Domain\Address;

class MarkerSyncContract
{
    private const ACCEPTED_COORDINATE_SOURCES = ['map', 'user', 'geolocation'];

    public function shouldAcceptIncomingSource(?string $source): bool
    {
        return in_array($source, self::ACCEPTED_COORDINATE_SOURCES, true);
    }

    public function precisionForIncomingSource(float $lat, float $lng, ?string $source, CoordinateTrustPolicy $policy): Precision
    {
        return $source === 'map'
            ? $policy->precisionForManualPointSelection($lat, $lng)
            : $policy->precisionForFieldGeocode($lat, $lng);
    }

    public function outgoingMarkerPayload(float $lat, float $lng, string $precision): array
    {
        return [
            'lat' => $lat,
            'lng' => $lng,
            'precision' => $precision,
        ];
    }
}
