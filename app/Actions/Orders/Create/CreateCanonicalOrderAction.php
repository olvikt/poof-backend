<?php

namespace App\Actions\Orders\Create;

use App\DTO\Orders\CanonicalOrderCreatePayload;
use App\Models\ClientAddress;
use App\Models\Order;
use App\Models\User;

class CreateCanonicalOrderAction
{
    public function handle(User $client, CanonicalOrderCreatePayload $payload, ClientAddress $address): Order
    {
        return Order::createFromCanonicalContract(
            $payload->toOrderAttributes(
                clientId: (int) $client->id,
                address: $address,
                price: $this->calculatePrice($payload->bagsCount()),
            )
        );
    }

    private function calculatePrice(int $bags): int
    {
        return 100 + ($bags - 1) * 25;
    }
}
