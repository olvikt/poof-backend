<?php

namespace App\Services\Address;

use App\DTO\Address\AddressFormData;
use App\DTO\Address\PersistAddressData;

class PrepareAddressSavePayload
{
    public function execute(AddressFormData $data): PersistAddressData
    {
        [$street, $city] = $this->resolveStreetAndCity($data);

        return PersistAddressData::fromCanonical([
            'label' => $data->label,
            'title' => $data->title,
            'building_type' => $data->buildingType,
            'address_text' => $this->normalizeSearch($data->search),
            'city' => $city,
            'region' => $data->region,
            'street' => $street,
            'house' => $this->normalizeHouse($data->house),
            'lat' => $data->lat,
            'lng' => $data->lng,
            'entrance' => $data->buildingType === 'apartment' ? $data->entrance : null,
            'intercom' => $data->buildingType === 'apartment' ? $data->intercom : null,
            'floor' => $data->buildingType === 'apartment' ? $data->floor : null,
            'apartment' => $data->buildingType === 'apartment' ? $data->apartment : null,
            'geocode_source' => 'manual',
            'geocode_accuracy' => 'exact',
            'geocoded_at' => now(),
        ]);
    }

    public function applyFallback(AddressFormData $data): array
    {
        [$street, $city] = $this->resolveStreetAndCity($data);

        return [
            'street' => $street,
            'city' => $city,
        ];
    }

    private function resolveStreetAndCity(AddressFormData $data): array
    {
        $street = $this->normalizeStreet($data->street);
        $city = $this->normalizeString($data->city);

        if ($street === null && $data->search) {
            $parts = array_map('trim', explode(',', $data->search));
            $street = $this->normalizeStreet($parts[0] ?? null);

            if ($city === null && isset($parts[1])) {
                $city = $this->normalizeString($parts[1]);
            }
        }

        return [$street, $city];
    }

    private function normalizeStreet(?string $street): ?string
    {
        $street = trim((string) $street);
        if ($street === '') {
            return null;
        }

        return preg_replace('/^\s*\d+[\dA-Za-zА-Яа-яІЇЄієї\-\/]*\s*,\s*/u', '', $street) ?: null;
    }

    private function normalizeHouse(?string $house): ?string
    {
        $house = trim((string) $house);

        return $house !== '' ? $house : null;
    }

    private function normalizeSearch(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function normalizeString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
