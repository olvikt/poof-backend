<?php

declare(strict_types=1);

namespace App\Actions\Orders\Completion;

use App\Actions\Orders\Lifecycle\FinalizeCompletedOrderAction;
use App\Models\Order;
use App\Models\OrderCompletionRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoConfirmOrderCompletionRequestAction
{
    public function __construct(private readonly FinalizeCompletedOrderAction $finalizeAction)
    {
    }

    public function handle(int $completionRequestId): string
    {
        return (string) DB::transaction(function () use ($completionRequestId) {
            $request = OrderCompletionRequest::query()->whereKey($completionRequestId)->lockForUpdate()->first();
            if (! $request) {
                return 'missing';
            }

            if ($request->status !== OrderCompletionRequest::STATUS_AWAITING_CLIENT_CONFIRMATION) {
                Log::info('completion_auto_confirm_skipped', [
                    'flow' => 'order_completion_proof',
                    'order_id' => $request->order_id,
                    'completion_request_id' => $request->id,
                    'status_before' => $request->status,
                    'reason' => 'invalid_status',
                ]);

                return 'skipped';
            }

            if (! $request->auto_confirmation_due_at || $request->auto_confirmation_due_at->isFuture()) {
                Log::info('completion_auto_confirm_skipped', [
                    'flow' => 'order_completion_proof',
                    'order_id' => $request->order_id,
                    'completion_request_id' => $request->id,
                    'status_before' => $request->status,
                    'reason' => 'not_due',
                ]);

                return 'skipped';
            }

            $order = Order::query()->whereKey($request->order_id)->lockForUpdate()->first();
            $courier = User::query()->whereKey($request->courier_id)->first();
            if (! $order || ! $courier) {
                return 'missing';
            }

            $request->forceFill([
                'status' => OrderCompletionRequest::STATUS_AUTO_CONFIRMED,
                'client_confirmed_at' => now(),
            ])->save();

            $this->finalizeAction->finalizeLocked($order, $courier, 'order_completion_proof');

            Log::info('completion_auto_confirmed', [
                'flow' => 'order_completion_proof',
                'order_id' => $order->id,
                'completion_request_id' => $request->id,
                'courier_id' => $courier->id,
                'status_after' => $request->status,
            ]);

            return 'confirmed';
        });
    }
}
