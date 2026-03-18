<?php

namespace App\Services\Address;

use Illuminate\Support\Facades\Schema;

class FilterClientAddressPayload
{
    private ?array $addressColumns = null;

    public function execute(array $payload): array
    {
        $columns = $this->addressColumns ??= Schema::getColumnListing('client_addresses');

        return collect($payload)
            ->only($columns)
            ->all();
    }
}
