<?php

namespace App\Services\Address;

use App\DTO\Address\AddressPointData;
use App\DTO\Address\ResolvedAddressData;
use Illuminate\Support\Facades\Http;

class ResolveAddressFromPoint
{
    public function execute(AddressPointData $point): ?ResolvedAddressData
    {
        $result = Http::timeout(10)
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => config('app.name', 'Poof') . '/1.0',
            ])
            ->get('https://nominatim.openstreetmap.org/reverse', [
                'format' => 'json',
                'lat' => $point->lat,
                'lon' => $point->lng,
                'addressdetails' => 1,
            ]);

        if (! $result->successful()) {
            return null;
        }

        $payload = $result->json();

        if (! is_array($payload)) {
            return null;
        }

        $address = $payload['address'] ?? null;

        if (! is_array($address)) {
            return null;
        }

        $street = $this->normalizeStreet(
            $address['road'] ?? $address['pedestrian'] ?? $address['street'] ?? null
        );
        $house = $this->normalizeHouse($address['house_number'] ?? null);
        $city = $this->normalizeString($address['city'] ?? $address['town'] ?? $address['village'] ?? null);
        $region = $this->normalizeString($address['state'] ?? $address['region'] ?? null);

        if ($house === null) {
            $house = $this->extractHouseFromDisplayName($payload['display_name'] ?? null);
        }

        return new ResolvedAddressData(
            street: $street,
            house: $house,
            city: $city,
            region: $region,
            search: $this->buildSearch($street, $house, $city, $region, $payload['label'] ?? null),
        );
    }

    public function buildSearch(
        ?string $street,
        ?string $house,
        ?string $city,
        ?string $region,
        mixed $label = null,
    ): string {
        $line1 = trim(implode(' ', array_filter([$street, $house])));
        $line2 = trim(implode(', ', array_filter([$city, $region])));

        return $this->normalizeSearch($label ?? trim(implode(', ', array_filter([$line1, $line2]))));
    }

    private function extractHouseFromDisplayName(?string $displayName): ?string
    {
        if (! $displayName) {
            return null;
        }

        if (preg_match(
            '/,\s*([0-9]+[0-9A-Za-zА-Яа-яІЇЄієї\-\/]*)\b/u',
            $displayName,
            $matches
        )) {
            return $this->normalizeHouse($matches[1]);
        }

        return null;
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

    private function normalizeSearch(mixed $value): string
    {
        if (is_array($value)) {
            return trim((string) ($value['label'] ?? $value['name'] ?? ''));
        }

        if (is_object($value)) {
            return trim((string) ($value->label ?? $value->name ?? ''));
        }

        return trim((string) $value);
    }

    private function normalizeString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
