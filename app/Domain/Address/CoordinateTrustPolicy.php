<?php

namespace App\Domain\Address;

class CoordinateTrustPolicy
{
    public function precisionForFieldGeocode(?float $lat, ?float $lng): Precision
    {
        return Precision::fromCoordinates($lat, $lng);
    }

    public function precisionForManualPointSelection(?float $lat, ?float $lng): Precision
    {
        return Precision::fromCoordinates($lat, $lng, true);
    }

    public function precisionForAddressBook(?float $lat, ?float $lng): Precision
    {
        return Precision::fromCoordinates($lat, $lng, true);
    }

    public function shouldAcceptFieldGeocode(Precision $currentPrecision): bool
    {
        return ! $currentPrecision->isExact();
    }

    public function shouldReverseFillHouse(bool $houseTouchedManually): bool
    {
        return ! $houseTouchedManually;
    }

    public function shouldRunHooksForProgrammaticUpdate(bool $suppressed): bool
    {
        return ! $suppressed;
    }

    public function shouldIgnoreIncomingCoords(
        ?float $existingLat,
        ?float $existingLng,
        Precision $currentPrecision,
        bool $selectedAddressLocked,
        float $incomingLat,
        float $incomingLng,
        ?string $source,
    ): bool {
        if ($source !== 'geolocation') {
            return false;
        }

        if ($selectedAddressLocked) {
            return $this->hasExistingPoint($existingLat, $existingLng)
                && (! $this->coordinatesMatch((float) $existingLat, $incomingLat)
                    || ! $this->coordinatesMatch((float) $existingLng, $incomingLng));
        }

        return $currentPrecision->isExact()
            && $this->hasExistingPoint($existingLat, $existingLng)
            && (! $this->coordinatesMatch((float) $existingLat, $incomingLat)
                || ! $this->coordinatesMatch((float) $existingLng, $incomingLng));
    }

    public function coordinatesMatch(float $left, float $right): bool
    {
        return abs($left - $right) <= 0.000001;
    }

    private function hasExistingPoint(?float $lat, ?float $lng): bool
    {
        return $lat !== null && $lng !== null;
    }
}
