<?php

namespace App\Actions\Orders\Create;

use App\DTO\Orders\LegacyWebOrderCreatePayload;
use App\Models\Order;

class CreateLegacyWebOrderAction
{
    public function handle(int $clientId, LegacyWebOrderCreatePayload $payload): Order
    {
        return Order::query()->create($payload->toOrderAttributes($clientId));
    }
}
