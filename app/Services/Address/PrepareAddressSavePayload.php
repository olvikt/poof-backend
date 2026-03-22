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

        if ($data->search) {
            $parts = $this->splitSearchParts($data->search);

            if ($street === null) {
                $street = $this->resolveStreetFromSearchParts($parts);
            }

            if ($city === null) {
                $city = $this->resolveCityFromSearchParts($parts);
            }
        }

        return [$street, $city];
    }

    private function splitSearchParts(?string $search): array
    {
        return array_values(array_filter(
            array_map(fn (string $part): string => trim($part), explode(',', (string) $search)),
            fn (string $part): bool => $part !== ''
        ));
    }

    private function resolveStreetFromSearchParts(array $parts): ?string
    {
        $streetParts = count($parts) > 1 ? array_slice($parts, 0, -1) : $parts;

        foreach ($streetParts as $part) {
            if ($this->isHouseNumberToken($part)) {
                continue;
            }

            $street = $this->normalizeStreet($part);

            if ($street !== null) {
                return $street;
            }
        }

        return null;
    }

    private function resolveCityFromSearchParts(array $parts): ?string
    {
        return $this->normalizeString($parts[count($parts) - 1] ?? null);
    }

    private function isHouseNumberToken(string $part): bool
    {
        return preg_match('/^\d+[\dA-Za-zА-Яа-яІЇЄієї\-\/]*$/u', trim($part)) === 1;
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
