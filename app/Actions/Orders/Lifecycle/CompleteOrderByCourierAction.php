<?php

declare(strict_types=1);

namespace App\Actions\Orders\Lifecycle;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CompleteOrderByCourierAction
{
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

            $lockedOrder->update([
                'status' => Order::STATUS_DONE,
                'completed_at' => now(),
            ]);

            $courier->markFree();

            $courier->update([
                'last_completed_at' => now(),
            ]);

            return true;
        });
    }
}
