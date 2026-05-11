<?php

declare(strict_types=1);

namespace App\Actions\Orders\Lifecycle;

use App\Models\Order;
use App\Models\User;
use App\Support\Orders\OrderLifecycleTransitionPolicy;
use Illuminate\Support\Facades\DB;

class CancelOrderAction
{
    public function __construct(private readonly OrderLifecycleTransitionPolicy $lifecyclePolicy)
    {
    }

    public function handle(Order $order): bool
    {
        return (bool) DB::transaction(function () use ($order) {
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedOrder || ! $lockedOrder->canBeCancelled()) {
                return false;
            }
            $this->lifecyclePolicy->assertTransition($lockedOrder->status, Order::STATUS_CANCELLED);

            $courier = null;

            if ($lockedOrder->courier_id !== null) {
                $courier = User::query()
                    ->whereKey($lockedOrder->courier_id)
                    ->lockForUpdate()
                    ->first();
            }

            $lockedOrder->forceFill([
                'status' => Order::STATUS_CANCELLED,
            ])->save();

            if ($courier instanceof User && $courier->isCourier()) {
                $courier->markFree();
            }

            return true;
        });
    }
}
