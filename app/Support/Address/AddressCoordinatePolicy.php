<?php

namespace App\Support\Address;

use App\Domain\Address\CoordinateTrustPolicy;

class AddressCoordinatePolicy
{
    public static function precisionForFieldGeocode(?float $lat, ?float $lng): AddressPrecision
    {
        return AddressPrecision::fromDomain(app(CoordinateTrustPolicy::class)->precisionForFieldGeocode($lat, $lng));
    }

    public static function precisionForManualPointSelection(?float $lat, ?float $lng): AddressPrecision
    {
        return AddressPrecision::fromDomain(app(CoordinateTrustPolicy::class)->precisionForManualPointSelection($lat, $lng));
    }

    public static function precisionForAddressBook(?float $lat, ?float $lng): AddressPrecision
    {
        return AddressPrecision::fromDomain(app(CoordinateTrustPolicy::class)->precisionForAddressBook($lat, $lng));
    }

    public static function shouldAcceptFieldGeocode(AddressPrecision $currentPrecision): bool
    {
        return app(CoordinateTrustPolicy::class)->shouldAcceptFieldGeocode($currentPrecision->toDomain());
    }

    public static function shouldReverseFillHouse(bool $houseTouchedManually): bool
    {
        return app(CoordinateTrustPolicy::class)->shouldReverseFillHouse($houseTouchedManually);
    }

    public static function shouldRunHooksForProgrammaticUpdate(bool $suppressed): bool
    {
        return app(CoordinateTrustPolicy::class)->shouldRunHooksForProgrammaticUpdate($suppressed);
    }
}
