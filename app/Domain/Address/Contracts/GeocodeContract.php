<?php

namespace App\Domain\Address\Contracts;

use App\DTO\Address\AddressFieldsData;
use App\DTO\Address\AddressPointData;
use App\DTO\Address\ResolvedAddressData;
use App\DTO\Address\ResolvedAddressPointData;

interface GeocodeContract
{
    public function resolveAddressPoint(AddressFieldsData $fields): ?ResolvedAddressPointData;

    public function resolveAddressFromPoint(AddressPointData $point): ?ResolvedAddressData;
}
