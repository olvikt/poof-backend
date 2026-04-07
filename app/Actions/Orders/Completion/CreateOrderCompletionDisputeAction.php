<?php

declare(strict_types=1);

namespace App\Actions\Orders\Completion;

use App\Models\Order;
use App\Models\OrderCompletionDispute;
use App\Models\OrderCompletionRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateOrderCompletionDisputeAction
{
    public function handle(Order $order, User $client, string $reasonCode, ?string $comment = null): bool
    {
        return (bool) DB::transaction(function () use ($order, $client, $reasonCode, $comment) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->first();
            if (! $lockedOrder || (int) $lockedOrder->client_id !== (int) $client->id) {
                return false;
            }

            $request = OrderCompletionRequest::query()->where('order_id', $lockedOrder->id)->lockForUpdate()->first();
            if (! $request) {
                return false;
            }

            if ($request->status !== OrderCompletionRequest::STATUS_AWAITING_CLIENT_CONFIRMATION) {
                Log::info('completion_invalid_transition_guard_hit', [
                    'flow' => 'order_completion_proof',
                    'order_id' => $lockedOrder->id,
                    'completion_request_id' => $request->id,
                    'client_id' => $client->id,
                    'status_before' => $request->status,
                    'reason_code' => $reasonCode,
                    'reason' => 'dispute_invalid_status',
                ]);

                return false;
            }

            if (OrderCompletionDispute::query()->where('completion_request_id', $request->id)->lockForUpdate()->exists()) {
                Log::info('completion_invalid_transition_guard_hit', [
                    'flow' => 'order_completion_proof',
                    'order_id' => $lockedOrder->id,
                    'completion_request_id' => $request->id,
                    'client_id' => $client->id,
                    'status_before' => $request->status,
                    'reason' => 'duplicate_dispute',
                ]);

                return false;
            }

            $statusBefore = $request->status;
            $request->forceFill(['status' => OrderCompletionRequest::STATUS_DISPUTED])->save();

            $dispute = OrderCompletionDispute::unguarded(fn () => OrderCompletionDispute::query()->create([
                'completion_request_id' => $request->id,
                'order_id' => $lockedOrder->id,
                'client_id' => $client->id,
                'courier_id' => $request->courier_id,
                'status' => OrderCompletionDispute::STATUS_OPEN,
                'reason_code' => $reasonCode,
                'comment' => $comment,
                'opened_at' => now(),
            ]));

            Log::info('completion_dispute_opened', [
                'flow' => 'order_completion_proof',
                'order_id' => $lockedOrder->id,
                'completion_request_id' => $request->id,
                'dispute_id' => $dispute->id,
                'client_id' => $client->id,
                'courier_id' => $request->courier_id,
                'status_before' => $statusBefore,
                'status_after' => $request->status,
                'reason_code' => $reasonCode,
            ]);

            return true;
        });
    }
}
