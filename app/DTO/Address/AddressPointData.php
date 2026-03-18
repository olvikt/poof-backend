<?php

namespace App\DTO\Address;

class AddressPointData
{
    public function __construct(
        public readonly float $lat,
        public readonly float $lng,
        public readonly ?string $source,
    ) {
    }
}
