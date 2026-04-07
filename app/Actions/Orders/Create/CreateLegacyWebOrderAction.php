<?php

namespace App\Actions\Orders\Create;

use App\DTO\Orders\LegacyWebOrderCreatePayload;
use App\Models\Order;
use App\Services\Orders\Completion\OrderCompletionPolicyAssignmentService;
use App\Support\Orders\OrderPromiseResolver;
use Illuminate\Support\Facades\Log;

class CreateLegacyWebOrderAction
{
    public function __construct(
        private readonly OrderPromiseResolver $promiseResolver,
        private readonly OrderCompletionPolicyAssignmentService $policyAssignment,
    ) {
    }

    public function handle(int $clientId, LegacyWebOrderCreatePayload $payload): Order
    {
        $attributes = $payload->toOrderAttributes($clientId);
        $attributes['completion_policy'] = $this->policyAssignment->assignForCreate($attributes['handover_type'] ?? null);
        $attributes = array_merge($attributes, $this->promiseResolver->resolveCreateAttributes($attributes));

        $order = Order::createFromLegacyWebContract($attributes);

        Log::info('order_completion_policy_assigned', [
            'order_id' => $order->id,
            'handover_type' => $attributes['handover_type'] ?? null,
            'completion_policy' => $order->completion_policy,
            'create_path' => 'legacy_web',
        ]);

        return $order;
    }
}
