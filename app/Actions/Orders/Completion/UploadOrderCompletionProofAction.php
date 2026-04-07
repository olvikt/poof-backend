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

class UploadOrderCompletionProofAction
{
    public function __construct(
        private readonly OrderCompletionPolicyResolver $policyResolver,
        private readonly StartOrderCompletionProofAction $startAction,
    ) {
    }

    /**
     * ADR: duplicate proof_type uploads are handled as in-place updates.
     * This keeps the API idempotent and deterministic for mobile retries.
     */
    public function handle(Order $order, User $courier, string $proofType, string $filePath, ?string $fileDisk = null): bool
    {
        $startedAt = microtime(true);

        return (bool) DB::transaction(function () use ($order, $courier, $proofType, $filePath, $fileDisk, $startedAt) {
            $request = $this->startAction->handle($order, $courier);

            if (! $request) {
                return false;
            }

            $lockedRequest = OrderCompletionRequest::query()->whereKey($request->id)->lockForUpdate()->first();

            if (! $lockedRequest || (int) $lockedRequest->courier_id !== (int) $courier->id) {
                return false;
            }

            $requiredProofs = $this->policyResolver->requiredProofTypes($lockedRequest->completion_policy);
            if (! in_array($proofType, $requiredProofs, true)) {
                return false;
            }

            $proof = OrderCompletionProof::query()
                ->where('completion_request_id', $lockedRequest->id)
                ->where('proof_type', $proofType)
                ->lockForUpdate()
                ->first();

            if ($proof) {
                $proof->forceFill([
                    'file_path' => $filePath,
                    'file_disk' => $fileDisk,
                    'uploaded_at' => now(),
                ])->save();
            } else {
                $proof = OrderCompletionProof::unguarded(fn () => OrderCompletionProof::query()->create([
                    'completion_request_id' => $lockedRequest->id,
                    'order_id' => $order->id,
                    'courier_id' => $courier->id,
                    'proof_type' => $proofType,
                    'file_path' => $filePath,
                    'file_disk' => $fileDisk,
                    'uploaded_at' => now(),
                ]));
            }

            $uploadedTypes = OrderCompletionProof::query()
                ->where('completion_request_id', $lockedRequest->id)
                ->pluck('proof_type')
                ->all();
            $allUploaded = count(array_diff($requiredProofs, $uploadedTypes)) === 0;

            $statusBefore = $lockedRequest->status;
            $lockedRequest->forceFill([
                'status' => $allUploaded ? OrderCompletionRequest::STATUS_READY_FOR_SUBMIT : OrderCompletionRequest::STATUS_DRAFT,
            ])->save();

            Log::info('completion_proof_uploaded', [
                'flow' => 'order_completion_proof',
                'order_id' => $order->id,
                'courier_id' => $courier->id,
                'completion_request_id' => $lockedRequest->id,
                'proof_type' => $proofType,
                'status_before' => $statusBefore,
                'status_after' => $lockedRequest->status,
                'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return true;
        });
    }
}
