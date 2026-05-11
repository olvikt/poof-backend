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

    public function handle(Order $order, string $flow = OrderLifecycleTransitionPolicy::FLOW_DEFAULT): bool
    {
        return (bool) DB::transaction(function () use ($order, $flow) {
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedOrder) {
                return false;
            }
            if ($flow === OrderLifecycleTransitionPolicy::FLOW_DEFAULT && ! $lockedOrder->canBeCancelled()) {
                return false;
            }
            $this->lifecyclePolicy->assertTransition($lockedOrder->status, Order::STATUS_CANCELLED, $flow);

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

    public function handleAdminOverride(Order $order): bool
    {
        return $this->handle($order, OrderLifecycleTransitionPolicy::FLOW_ADMIN_OVERRIDE);
    }
}
