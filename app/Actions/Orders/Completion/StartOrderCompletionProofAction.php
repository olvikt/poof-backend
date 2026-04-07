<?php

declare(strict_types=1);

namespace App\Actions\Orders\Completion;

use App\Models\Order;
use App\Models\OrderCompletionRequest;
use App\Models\User;
use App\Services\Orders\Completion\OrderCompletionPolicyResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StartOrderCompletionProofAction
{
    public function __construct(private readonly OrderCompletionPolicyResolver $policyResolver)
    {
    }

    public function handle(Order $order, User $courier): ?OrderCompletionRequest
    {
        return DB::transaction(function () use ($order, $courier): ?OrderCompletionRequest {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if (! $lockedOrder || ! $lockedOrder->canBeCompletedBy($courier)) {
                return null;
            }

            $policy = $this->policyResolver->resolveForOrder($lockedOrder);

            if ($policy === OrderCompletionRequest::POLICY_NONE) {
                return null;
            }

            $existing = OrderCompletionRequest::query()
                ->where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                Log::info('completion_duplicate_guard_hit', [
                    'flow' => 'order_completion_proof',
                    'order_id' => $lockedOrder->id,
                    'courier_id' => $courier->id,
                    'completion_request_id' => $existing->id,
                    'reason' => 'request_already_exists',
                ]);

                return $existing;
            }

            $created = OrderCompletionRequest::unguarded(fn () => OrderCompletionRequest::query()->create([
                'order_id' => $lockedOrder->id,
                'courier_id' => $courier->id,
                'completion_policy' => $policy,
                'status' => OrderCompletionRequest::STATUS_DRAFT,
            ]));

            Log::info('completion_proof_started', [
                'flow' => 'order_completion_proof',
                'order_id' => $lockedOrder->id,
                'courier_id' => $courier->id,
                'completion_request_id' => $created->id,
                'status_before' => null,
                'status_after' => $created->status,
            ]);

            return $created;
        });
    }
}
