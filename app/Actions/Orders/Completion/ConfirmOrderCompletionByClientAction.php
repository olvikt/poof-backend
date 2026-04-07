<?php

declare(strict_types=1);

namespace App\Actions\Orders\Completion;

use App\Actions\Orders\Lifecycle\FinalizeCompletedOrderAction;
use App\Models\Order;
use App\Models\OrderCompletionRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConfirmOrderCompletionByClientAction
{
    public function __construct(private readonly FinalizeCompletedOrderAction $finalizeAction)
    {
    }

    public function handle(Order $order, ?User $client = null): bool
    {
        return (bool) DB::transaction(function () use ($order, $client) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->first();
            if (! $lockedOrder) {
                return false;
            }

            if ($client && (int) $lockedOrder->client_id !== (int) $client->id) {
                return false;
            }

            $request = OrderCompletionRequest::query()
                ->where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->first();

            if (! $request) {
                return false;
            }

            if ($request->status === OrderCompletionRequest::STATUS_CLIENT_CONFIRMED || $request->status === OrderCompletionRequest::STATUS_AUTO_CONFIRMED) {
                Log::info('completion_duplicate_guard_hit', [
                    'flow' => 'order_completion_proof',
                    'order_id' => $lockedOrder->id,
                    'courier_id' => $request->courier_id,
                    'client_id' => $client?->id,
                    'completion_request_id' => $request->id,
                    'reason' => 'already_confirmed',
                ]);

                return true;
            }

            if ($request->status !== OrderCompletionRequest::STATUS_AWAITING_CLIENT_CONFIRMATION) {
                Log::info('completion_invalid_transition_guard_hit', [
                    'flow' => 'order_completion_proof',
                    'order_id' => $lockedOrder->id,
                    'client_id' => $client?->id,
                    'completion_request_id' => $request->id,
                    'status_before' => $request->status,
                    'reason' => 'confirm_invalid_status',
                ]);

                return false;
            }

            $statusBefore = $request->status;
            $request->forceFill([
                'status' => OrderCompletionRequest::STATUS_CLIENT_CONFIRMED,
                'client_confirmed_at' => now(),
            ])->save();

            $courier = User::query()->whereKey($request->courier_id)->first();
            if (! $courier instanceof User) {
                return false;
            }

            Log::info('completion_client_confirmed', [
                'flow' => 'order_completion_proof',
                'order_id' => $lockedOrder->id,
                'courier_id' => $courier->id,
                'client_id' => $client?->id,
                'completion_request_id' => $request->id,
                'status_before' => $statusBefore,
                'status_after' => $request->status,
            ]);

            return $this->finalizeAction->finalizeLocked($lockedOrder, $courier, 'order_completion_proof');
        });
    }
}
