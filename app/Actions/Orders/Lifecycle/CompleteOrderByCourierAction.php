<?php

declare(strict_types=1);

namespace App\Actions\Orders\Lifecycle;

use App\Actions\Orders\Completion\StartOrderCompletionProofAction;
use App\Actions\Orders\Completion\SubmitOrderCompletionByCourierAction;
use App\Models\Order;
use App\Models\OrderCompletionRequest;
use App\Models\User;
use App\Services\Orders\Completion\OrderCompletionPolicyResolver;
use Illuminate\Support\Facades\DB;

class CompleteOrderByCourierAction
{
    public function __construct(
        private readonly OrderCompletionPolicyResolver $policyResolver,
        private readonly StartOrderCompletionProofAction $startProofAction,
        private readonly SubmitOrderCompletionByCourierAction $submitProofAction,
        private readonly FinalizeCompletedOrderAction $finalizeCompletedOrderAction,
    ) {
    }

    /**
     * Завершити виконання (курʼєр-safe)
     */
    public function handle(Order $order, User $courier): bool
    {
        return (bool) DB::transaction(function () use ($order, $courier) {
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedOrder || ! $lockedOrder->canBeCompletedBy($courier)) {
                return false;
            }

            $policy = $this->policyResolver->resolveForOrder($lockedOrder);

            if ($policy === OrderCompletionRequest::POLICY_NONE) {
                return $this->finalizeCompletedOrderAction->finalizeLocked($lockedOrder, $courier);
            }

            // Door pickup policy: completion requires proof submission and client confirmation.
            $this->startProofAction->handle($lockedOrder, $courier);

            return $this->submitProofAction->handle($lockedOrder, $courier);
        });
    }
}
