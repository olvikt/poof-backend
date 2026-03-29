<?php

namespace App\Services\Address;

use App\Domain\Address\Contracts\GeocodeContract;
use App\DTO\Address\AddressFieldsData;
use App\DTO\Address\AddressPointData;
use App\DTO\Address\ResolvedAddressData;
use App\DTO\Address\ResolvedAddressPointData;

class AddressGeocoding implements GeocodeContract
{
    public function __construct(
        private readonly ResolveAddressPointFromFields $resolveAddressPointFromFields,
        private readonly ResolveAddressFromPoint $resolveAddressFromPoint,
    ) {
    }

    public function resolveAddressPoint(AddressFieldsData $fields): ?ResolvedAddressPointData
    {
        return $this->resolveAddressPointFromFields->execute($fields);
    }

    public function resolveAddressFromPoint(AddressPointData $point): ?ResolvedAddressData
    {
        return $this->resolveAddressFromPoint->execute($point);
    }
}
