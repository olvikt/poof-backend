<?php

declare(strict_types=1);

namespace App\Actions\Orders\Completion\Admin;

use App\Actions\Orders\Lifecycle\FinalizeCompletedOrderAction;
use App\Models\Order;
use App\Models\OrderCompletionDispute;
use App\Models\OrderCompletionRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResolveOrderCompletionDisputeAction
{
    public function __construct(private readonly FinalizeCompletedOrderAction $finalizeAction)
    {
    }

    public function handle(OrderCompletionDispute $dispute, User $resolver, bool $approveCompletion, ?string $resolutionNote = null): bool
    {
        return (bool) DB::transaction(function () use ($dispute, $resolver, $approveCompletion, $resolutionNote) {
            $lockedDispute = OrderCompletionDispute::query()->whereKey($dispute->id)->lockForUpdate()->first();
            if (! $lockedDispute) {
                return false;
            }

            if (in_array($lockedDispute->status, [OrderCompletionDispute::STATUS_RESOLVED_CONFIRMED, OrderCompletionDispute::STATUS_RESOLVED_REJECTED], true)) {
                return true;
            }

            if (! in_array($lockedDispute->status, [OrderCompletionDispute::STATUS_OPEN, OrderCompletionDispute::STATUS_UNDER_REVIEW], true)) {
                return false;
            }

            $request = OrderCompletionRequest::query()->whereKey($lockedDispute->completion_request_id)->lockForUpdate()->first();
            $order = $request ? Order::query()->whereKey($request->order_id)->lockForUpdate()->first() : null;
            $courier = $request ? User::query()->whereKey($request->courier_id)->first() : null;
            if (! $request || ! $order || ! $courier) {
                return false;
            }

            if ($approveCompletion) {
                $request->forceFill([
                    'status' => OrderCompletionRequest::STATUS_CLIENT_CONFIRMED,
                    'client_confirmed_at' => now(),
                ])->save();
                $this->finalizeAction->finalizeLocked($order, $courier, 'order_completion_proof');
                $targetStatus = OrderCompletionDispute::STATUS_RESOLVED_CONFIRMED;
                $resolution = 'confirmed';
            } else {
                $request->forceFill([
                    'status' => OrderCompletionRequest::STATUS_CANCELLED,
                ])->save();
                $targetStatus = OrderCompletionDispute::STATUS_RESOLVED_REJECTED;
                $resolution = 'rejected';
            }

            $lockedDispute->forceFill([
                'status' => $targetStatus,
                'resolver_user_id' => $resolver->id,
                'resolved_at' => now(),
                'resolution_note' => $resolutionNote,
            ])->save();

            Log::info('completion_dispute_resolved', [
                'flow' => 'order_completion_proof',
                'order_id' => $lockedDispute->order_id,
                'completion_request_id' => $lockedDispute->completion_request_id,
                'dispute_id' => $lockedDispute->id,
                'resolver_user_id' => $resolver->id,
                'resolution' => $resolution,
            ]);

            return true;
        });
    }
}
