<?php

namespace App\Actions\Orders\Create;

use App\DTO\Orders\LegacyWebOrderCreatePayload;
use App\Models\Order;
use App\Support\Orders\OrderPromiseResolver;

class CreateLegacyWebOrderAction
{
    public function __construct(private readonly OrderPromiseResolver $promiseResolver)
    {
    }

    public function handle(int $clientId, LegacyWebOrderCreatePayload $payload): Order
    {
        $attributes = $payload->toOrderAttributes($clientId);
        $attributes = array_merge($attributes, $this->promiseResolver->resolveCreateAttributes($attributes));

        return Order::createFromLegacyWebContract($attributes);
    }
}
