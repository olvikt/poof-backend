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

    public function handle(Order $order): bool
    {
        return (bool) DB::transaction(function () use ($order) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->first();
            if (! $lockedOrder) {
                return false;
            }

            $request = OrderCompletionRequest::query()
                ->where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->first();

            if (! $request) {
                return false;
            }

            if ($request->status === OrderCompletionRequest::STATUS_CLIENT_CONFIRMED) {
                Log::info('completion_duplicate_guard_hit', [
                    'flow' => 'order_completion_proof',
                    'order_id' => $lockedOrder->id,
                    'courier_id' => $request->courier_id,
                    'completion_request_id' => $request->id,
                    'reason' => 'already_client_confirmed',
                ]);

                return true;
            }

            if ($request->status !== OrderCompletionRequest::STATUS_AWAITING_CLIENT_CONFIRMATION) {
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
                'completion_request_id' => $request->id,
                'status_before' => $statusBefore,
                'status_after' => $request->status,
            ]);

            return $this->finalizeAction->finalizeLocked($lockedOrder, $courier, 'order_completion_proof');
        });
    }
}
