<?php

declare(strict_types=1);

namespace App\Services\Orders\Completion;

use App\Models\Order;

class OrderCompletionPolicyAssignmentService
{
    public function assignForCreate(?string $handoverType): string
    {
        return $handoverType === Order::HANDOVER_DOOR
            ? Order::COMPLETION_POLICY_DOOR_TWO_PHOTO_CLIENT_CONFIRM
            : Order::COMPLETION_POLICY_NONE;
    }
}
