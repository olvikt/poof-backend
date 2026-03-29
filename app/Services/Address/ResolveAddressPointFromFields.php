<?php

namespace App\Services\Address;

use App\DTO\Address\AddressFieldsData;
use App\DTO\Address\ResolvedAddressPointData;
use App\Domain\Address\AddressParser;
use Illuminate\Support\Facades\Http;

class ResolveAddressPointFromFields
{
    public function __construct(private readonly AddressParser $parser)
    {
    }

    public function execute(AddressFieldsData $fields): ?ResolvedAddressPointData
    {
        $prepared = $this->prepareQuery($fields);

        if ($prepared === null) {
            return null;
        }

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->get(url('/api/geocode'), [
                    'q' => $prepared,
                    'lat' => $fields->lat,
                    'lng' => $fields->lng,
                ]);

            if (! $response->successful()) {
                return null;
            }

            $item = $response->json('0');

            if (! is_array($item) || ! isset($item['lat'], $item['lng'])) {
                return null;
            }

            return new ResolvedAddressPointData(
                lat: (float) $item['lat'],
                lng: (float) $item['lng'],
                query: $prepared,
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function prepareQuery(AddressFieldsData $fields): ?string
    {
        $house = $this->parser->normalizeString($fields->house);

        if ($house === null) {
            return null;
        }

        $street = $this->parser->normalizeString($fields->street);
        $city = $this->parser->normalizeString($fields->city);

        if ($street === null) {
            [$fallbackStreet, $fallbackCity] = $this->parser->extractStreetAndCityFromSearch($fields->search);
            $street = $fallbackStreet;
            $city ??= $fallbackCity;
        }

        if ($street === null) {
            return null;
        }

        $query = $street . ', ' . $house;

        if ($city !== null) {
            $query .= ', ' . $city;
        }

        return $query;
    }

}
