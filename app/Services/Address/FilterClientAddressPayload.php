<?php

namespace App\Services\Address;

use Illuminate\Support\Facades\Schema;

class FilterClientAddressPayload
{
    private const ALLOWED_PAYLOAD_FIELDS = [
        'label',
        'title',
        'building_type',
        'address_text',
        'city',
        'region',
        'street',
        'house',
        'entrance',
        'intercom',
        'floor',
        'apartment',
        'lat',
        'lng',
        'place_id',
        'geocode_source',
        'geocode_accuracy',
        'geocoded_at',
    ];

    private ?array $allowedPayloadColumns = null;

    public function execute(array $payload): array
    {
        $columns = $this->allowedPayloadColumns ??= array_values(array_intersect(
            Schema::getColumnListing('client_addresses'),
            self::ALLOWED_PAYLOAD_FIELDS,
        ));

        return collect($payload)
            ->only($columns)
            ->all();
    }
}
