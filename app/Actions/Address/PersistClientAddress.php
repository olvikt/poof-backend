<?php

namespace App\Actions\Address;

use App\DTO\Address\AddressFormData;
use App\DTO\Address\PersistAddressData;
use App\Models\ClientAddress;

class PersistClientAddress
{
    public function execute(AddressFormData $formData, PersistAddressData $payload, int $userId): void
    {
        if ($formData->isEdit()) {
            ClientAddress::query()
                ->where('id', $formData->addressId)
                ->where('user_id', $userId)
                ->firstOrFail()
                ->updateFromClient($payload->toArray());

            return;
        }

        ClientAddress::createForUser($userId, $payload->toArray());
    }
}
