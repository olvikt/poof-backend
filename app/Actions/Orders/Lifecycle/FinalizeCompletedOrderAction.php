<?php

declare(strict_types=1);

namespace App\Actions\Orders\Lifecycle;

use App\Models\Order;
use App\Models\User;
use App\Services\Courier\Earnings\CourierEarningsSettlementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FinalizeCompletedOrderAction
{
    public function __construct(private readonly CourierEarningsSettlementService $earningsSettlementService)
    {
    }

    public function handle(Order $order, User $courier, string $flow = 'legacy_completion'): bool
    {
        return (bool) DB::transaction(function () use ($order, $courier, $flow) {
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedOrder) {
                return false;
            }

            return $this->finalizeLocked($lockedOrder, $courier, $flow);
        });
    }

    public function finalizeLocked(Order $lockedOrder, User $courier, string $flow = 'legacy_completion'): bool
    {
        if ($lockedOrder->status === Order::STATUS_DONE && $lockedOrder->completed_at !== null) {
            Log::info('completion_duplicate_guard_hit', [
                'flow' => $flow,
                'order_id' => $lockedOrder->id,
                'courier_id' => $courier->id,
                'reason' => 'already_finalized',
            ]);

            return true;
        }

        if (! $lockedOrder->canBeCompletedBy($courier)) {
            return false;
        }

        $statusBefore = $lockedOrder->status;

        $lockedOrder->forceFill([
            'status' => Order::STATUS_DONE,
            'completed_at' => now(),
        ])->save();

        $courier->markFree();
        $courier->update([
            'last_completed_at' => now(),
        ]);

        $this->earningsSettlementService->settleForOrder($lockedOrder->fresh());

        Log::info('completion_finalized', [
            'flow' => $flow,
            'order_id' => $lockedOrder->id,
            'courier_id' => $courier->id,
            'status_before' => $statusBefore,
            'status_after' => Order::STATUS_DONE,
        ]);

        return true;
    }
}
