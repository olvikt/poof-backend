<?php

namespace App\Services\Address;

use App\DTO\Address\AddressPointData;
use App\DTO\Address\ResolvedAddressData;
use App\Domain\Address\AddressParser;
use Illuminate\Support\Facades\Http;

class ResolveAddressFromPoint
{
    public function __construct(private readonly AddressParser $parser)
    {
    }

    public function execute(AddressPointData $point): ?ResolvedAddressData
    {
        try {
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

            $street = $this->parser->normalizeStreet(
                $address['road'] ?? $address['pedestrian'] ?? $address['street'] ?? null
            );
            $house = $this->parser->normalizeHouse($address['house_number'] ?? null);
            $city = $this->parser->normalizeString($address['city'] ?? $address['town'] ?? $address['village'] ?? null);
            $region = $this->parser->normalizeString($address['state'] ?? $address['region'] ?? null);

            if ($house === null) {
                $house = $this->extractHouseFromDisplayName($payload['display_name'] ?? null);
            }

            return new ResolvedAddressData(
                street: $street,
                house: $house,
                city: $city,
                region: $region,
                search: $this->parser->buildSearch($street, $house, $city, $region, $payload['label'] ?? null),
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractHouseFromDisplayName(?string $displayName): ?string
    {
        if (! $displayName) {
            return null;
        }

        foreach (preg_split('/\s*,\s*/u', $displayName) ?: [] as $segment) {
            $house = $this->parser->normalizeHouse($segment);

            if ($house !== null) {
                return $house;
            }
        }

        return null;
    }

}
