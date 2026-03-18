<?php

namespace App\DTO\Address;

class ResolvedAddressPointData
{
    public function __construct(
        public readonly float $lat,
        public readonly float $lng,
        public readonly ?string $query = null,
    ) {
    }
}
