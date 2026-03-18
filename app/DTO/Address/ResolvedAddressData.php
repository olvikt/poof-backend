<?php

namespace App\DTO\Address;

class ResolvedAddressData
{
    public function __construct(
        public readonly ?string $street,
        public readonly ?string $house,
        public readonly ?string $city,
        public readonly ?string $region,
        public readonly ?string $search,
    ) {
    }
}
