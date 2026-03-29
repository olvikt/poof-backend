<?php

namespace App\Domain\Address;

class AddressParser
{
    public function normalizeSearch(mixed $value): string
    {
        if (is_array($value)) {
            return trim((string) ($value['label'] ?? $value['name'] ?? ''));
        }

        if (is_object($value)) {
            return trim((string) ($value->label ?? $value->name ?? ''));
        }

        return trim((string) $value);
    }

    public function normalizeStreet(?string $street): ?string
    {
        $street = trim((string) $street);

        if ($street === '') {
            return null;
        }

        return preg_replace('/^\s*\d+[\dA-Za-zА-Яа-яІЇЄієї\-\/]*\s*,\s*/u', '', $street) ?: null;
    }

    public function normalizeHouse(?string $house): ?string
    {
        $house = preg_replace('/\s+/u', ' ', trim((string) $house));

        if ($house === '' || ! preg_match('/^\d/u', $house)) {
            return null;
        }

        if (preg_match('/^(\d+[\dA-Za-zА-Яа-яІЇЄієї\-\/]*)(?:\s*(?:к|корп(?:\.|ус)?)\s*(\d+[A-Za-zА-Яа-яІЇЄієї\-\/]*))?$/ui', $house, $matches)) {
            $base = $matches[1];
            $corpus = $matches[2] ?? null;

            return $corpus ? sprintf('%s к%s', $base, $corpus) : $base;
        }

        return $house;
    }

    public function splitSearchParts(?string $search): array
    {
        return array_values(array_filter(
            array_map(fn (string $part): string => trim($part), explode(',', (string) $search)),
            fn (string $part): bool => $part !== ''
        ));
    }

    public function extractStreetAndCityFromSearch(?string $search): array
    {
        $parts = $this->splitSearchParts($search);

        return [
            $this->normalizeString($parts[0] ?? null),
            $this->normalizeString($parts[1] ?? null),
        ];
    }

    public function buildSearch(?string $street, ?string $house, ?string $city, ?string $region, mixed $label = null): string
    {
        $line1 = trim(implode(' ', array_filter([$street, $house])));
        $line2 = trim(implode(', ', array_filter([$city, $region])));

        return $this->normalizeSearch($label ?? trim(implode(', ', array_filter([$line1, $line2]))));
    }

    public function normalizeString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    public function isHouseNumberToken(string $part): bool
    {
        return preg_match('/^\d+[\dA-Za-zА-Яа-яІЇЄієї\-\/]*$/u', trim($part)) === 1;
    }
}
