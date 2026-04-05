<?php

namespace App\Actions\Orders\Create;

use App\DTO\Orders\CanonicalOrderCreatePayload;
use App\Models\ClientAddress;
use App\Models\Order;
use App\Support\Orders\OrderPromiseResolver;
use App\Models\User;

class CreateCanonicalOrderAction
{
    public function __construct(private readonly OrderPromiseResolver $promiseResolver)
    {
    }

    public function handle(User $client, CanonicalOrderCreatePayload $payload, ClientAddress $address): Order
    {
        $attributes = $payload->toOrderAttributes(
            clientId: (int) $client->id,
            address: $address,
            price: $this->calculatePrice($payload->bagsCount()),
        );

        $attributes = array_merge($attributes, $this->promiseResolver->resolveCreateAttributes($attributes));

        return Order::createFromCanonicalContract(
            $attributes
        );
    }

    private function calculatePrice(int $bags): int
    {
        return Order::calcPriceByBags($bags);
    }
}
