<?php

namespace App\DTO\Address;

use App\Livewire\Client\AddressForm;

class AddressFormData
{
    public function __construct(
        public readonly ?int $addressId,
        public readonly string $label,
        public readonly ?string $title,
        public readonly string $buildingType,
        public readonly ?string $search,
        public readonly ?float $lat,
        public readonly ?float $lng,
        public readonly ?string $city,
        public readonly ?string $region,
        public readonly ?string $street,
        public readonly ?string $house,
        public readonly ?string $entrance,
        public readonly ?string $intercom,
        public readonly ?string $floor,
        public readonly ?string $apartment,
    ) {
    }

    public static function fromComponent(AddressForm $component): self
    {
        return new self(
            addressId: $component->addressId,
            label: $component->label,
            title: $component->title,
            buildingType: $component->building_type,
            search: $component->search,
            lat: $component->lat,
            lng: $component->lng,
            city: $component->city,
            region: $component->region,
            street: $component->street,
            house: $component->house,
            entrance: $component->entrance,
            intercom: $component->intercom,
            floor: $component->floor,
            apartment: $component->apartment,
        );
    }

    public function isEdit(): bool
    {
        return (bool) $this->addressId;
    }
}
