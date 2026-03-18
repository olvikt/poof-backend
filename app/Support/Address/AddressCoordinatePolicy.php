<?php

namespace App\Support\Address;

class AddressCoordinatePolicy
{
    public static function precisionForFieldGeocode(?float $lat, ?float $lng): AddressPrecision
    {
        return AddressPrecision::fromCoordinates($lat, $lng);
    }

    public static function precisionForManualPointSelection(?float $lat, ?float $lng): AddressPrecision
    {
        return AddressPrecision::fromCoordinates($lat, $lng, true);
    }

    public static function precisionForAddressBook(?float $lat, ?float $lng): AddressPrecision
    {
        return AddressPrecision::fromCoordinates($lat, $lng, true);
    }

    public static function shouldAcceptFieldGeocode(AddressPrecision $currentPrecision): bool
    {
        return ! $currentPrecision->isExact();
    }

    public static function shouldReverseFillHouse(bool $houseTouchedManually): bool
    {
        return ! $houseTouchedManually;
    }

    public static function shouldRunHooksForProgrammaticUpdate(bool $suppressed): bool
    {
        return ! $suppressed;
    }
}
