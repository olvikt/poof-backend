<?php

declare(strict_types=1);

namespace App\Services\Orders\Completion;

use App\Models\Order;
use App\Models\OrderCompletionProof;
use App\Models\OrderCompletionRequest;

class OrderCompletionPolicyResolver
{
    public function resolveForOrder(Order $order): string
    {
        if ($order->handover_type === Order::HANDOVER_DOOR) {
            return OrderCompletionRequest::POLICY_DOOR_TWO_PHOTO_CLIENT_CONFIRM;
        }

        return OrderCompletionRequest::POLICY_NONE;
    }

    /** @return list<string> */
    public function requiredProofTypes(string $policy): array
    {
        if ($policy === OrderCompletionRequest::POLICY_DOOR_TWO_PHOTO_CLIENT_CONFIRM) {
            return [
                OrderCompletionProof::TYPE_DOOR_PHOTO,
                OrderCompletionProof::TYPE_CONTAINER_PHOTO,
            ];
        }

        return [];
    }

    public function requiresProofFlow(Order $order): bool
    {
        return $this->resolveForOrder($order) !== OrderCompletionRequest::POLICY_NONE;
    }
}
