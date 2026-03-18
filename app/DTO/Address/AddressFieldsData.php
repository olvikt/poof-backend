<?php

namespace App\DTO\Address;

class AddressFieldsData
{
    public function __construct(
        public readonly ?string $street,
        public readonly ?string $house,
        public readonly ?string $city,
        public readonly ?string $search,
        public readonly ?float $lat,
        public readonly ?float $lng,
    ) {
    }
}
