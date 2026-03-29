<?php

namespace App\Services\Address;

use App\DTO\Address\AddressFormData;
use App\DTO\Address\PersistAddressData;
use App\Domain\Address\AddressParser;

class PrepareAddressSavePayload
{
    private AddressParser $parser;

    public function __construct(?AddressParser $parser = null)
    {
        $this->parser = $parser ?? new AddressParser();
    }

    public function execute(AddressFormData $data): PersistAddressData
    {
        [$street, $city] = $this->resolveStreetAndCity($data);

        return PersistAddressData::fromCanonical([
            'label' => $data->label,
            'title' => $data->title,
            'building_type' => $data->buildingType,
            'address_text' => $this->normalizedOptional($this->parser->normalizeSearch($data->search)),
            'city' => $city,
            'region' => $data->region,
            'street' => $street,
            'house' => $this->normalizedOptional($this->parser->normalizeSearch($data->house)),
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
        $street = $this->parser->normalizeStreet($data->street);
        $city = $this->parser->normalizeString($data->city);

        if ($data->search) {
            $parts = $this->parser->splitSearchParts($data->search);

            if ($street === null) {
                $street = $this->resolveStreetFromSearchParts($parts);
            }

            if ($city === null) {
                $city = $this->resolveCityFromSearchParts($parts);
            }
        }

        return [$street, $city];
    }

    private function resolveStreetFromSearchParts(array $parts): ?string
    {
        $streetParts = count($parts) > 1 ? array_slice($parts, 0, -1) : $parts;

        foreach ($streetParts as $part) {
            if ($this->isHouseNumberToken($part)) {
                continue;
            }

            $street = $this->parser->normalizeStreet($part);

            if ($street !== null) {
                return $street;
            }
        }

        return null;
    }

    private function resolveCityFromSearchParts(array $parts): ?string
    {
        return $this->parser->normalizeString($parts[count($parts) - 1] ?? null);
    }

    private function isHouseNumberToken(string $part): bool
    {
        return $this->parser->isHouseNumberToken($part);
    }

    private function normalizedOptional(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
