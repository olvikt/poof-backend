<?php

namespace App\Actions\Orders\Create;

use App\DTO\Orders\CanonicalOrderCreatePayload;
use App\Models\ClientAddress;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\Completion\OrderCompletionPolicyAssignmentService;
use App\Support\Orders\OrderPromiseResolver;
use Illuminate\Support\Facades\Log;

class CreateCanonicalOrderAction
{
    public function __construct(
        private readonly OrderPromiseResolver $promiseResolver,
        private readonly OrderCompletionPolicyAssignmentService $policyAssignment,
    ) {
    }

    public function handle(User $client, CanonicalOrderCreatePayload $payload, ClientAddress $address): Order
    {
        $attributes = $payload->toOrderAttributes(
            clientId: (int) $client->id,
            address: $address,
            price: $this->calculatePrice($payload->bagsCount()),
        );

        $attributes['completion_policy'] = $this->policyAssignment->assignForCreate($attributes['handover_type'] ?? null);
        $attributes = array_merge($attributes, $this->promiseResolver->resolveCreateAttributes($attributes));

        $order = Order::createFromCanonicalContract($attributes);

        Log::info('order_completion_policy_assigned', [
            'order_id' => $order->id,
            'handover_type' => $attributes['handover_type'] ?? null,
            'completion_policy' => $order->completion_policy,
            'create_path' => 'canonical_api',
        ]);

        return $order;
    }

    private function calculatePrice(int $bags): int
    {
        return Order::calcPriceByBags($bags);
    }
}
