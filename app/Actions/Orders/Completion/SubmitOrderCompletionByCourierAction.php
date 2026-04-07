<?php

declare(strict_types=1);

namespace App\Actions\Orders\Completion;

use App\Models\Order;
use App\Models\OrderCompletionProof;
use App\Models\OrderCompletionRequest;
use App\Models\User;
use App\Services\Orders\Completion\OrderCompletionPolicyResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubmitOrderCompletionByCourierAction
{
    public function __construct(private readonly OrderCompletionPolicyResolver $policyResolver)
    {
    }

    public function handle(Order $order, User $courier): bool
    {
        return (bool) DB::transaction(function () use ($order, $courier) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->first();
            if (! $lockedOrder || ! $lockedOrder->canBeCompletedBy($courier)) {
                return false;
            }

            $request = OrderCompletionRequest::query()
                ->where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->first();

            if (! $request) {
                Log::info('completion_submit_rejected', [
                    'flow' => 'order_completion_proof',
                    'order_id' => $lockedOrder->id,
                    'courier_id' => $courier->id,
                    'reason' => 'request_not_started',
                ]);

                return false;
            }

            if ((int) $request->courier_id !== (int) $courier->id) {
                return false;
            }

            if ($request->status === OrderCompletionRequest::STATUS_AWAITING_CLIENT_CONFIRMATION) {
                Log::info('completion_duplicate_guard_hit', [
                    'flow' => 'order_completion_proof',
                    'order_id' => $lockedOrder->id,
                    'courier_id' => $courier->id,
                    'completion_request_id' => $request->id,
                    'reason' => 'already_submitted',
                ]);

                return true;
            }

            $requiredProofs = $this->policyResolver->requiredProofTypes($request->completion_policy);
            $uploadedProofs = OrderCompletionProof::query()
                ->where('completion_request_id', $request->id)
                ->pluck('proof_type')
                ->all();

            $missingProofs = array_values(array_diff($requiredProofs, $uploadedProofs));
            if ($missingProofs !== []) {
                Log::info('completion_submit_rejected', [
                    'flow' => 'order_completion_proof',
                    'order_id' => $lockedOrder->id,
                    'courier_id' => $courier->id,
                    'completion_request_id' => $request->id,
                    'reason' => 'missing_required_proofs',
                ]);

                return false;
            }

            $statusBefore = $request->status;
            $request->forceFill([
                'status' => OrderCompletionRequest::STATUS_AWAITING_CLIENT_CONFIRMATION,
                'submitted_at' => now(),
                'auto_confirmation_due_at' => now()->addHours(max(1, (int) config('order_completion_proof.auto_confirm_hours', 24))),
            ])->save();

            Log::info('completion_submitted', [
                'flow' => 'order_completion_proof',
                'order_id' => $lockedOrder->id,
                'courier_id' => $courier->id,
                'completion_request_id' => $request->id,
                'status_before' => $statusBefore,
                'status_after' => $request->status,
            ]);

            return true;
        });
    }
}
