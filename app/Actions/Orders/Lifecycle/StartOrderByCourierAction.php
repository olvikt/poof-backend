<?php

declare(strict_types=1);

namespace App\Actions\Orders\Lifecycle;

use App\Models\Order;
use App\Models\User;
use App\Support\Orders\OrderLifecycleTransitionPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StartOrderByCourierAction
{
    public function __construct(private readonly OrderLifecycleTransitionPolicy $lifecyclePolicy)
    {
    }
    /**
     * Почати виконання (курʼєр-safe)
     */
    public function handle(Order $order, User $courier): bool
    {
        return (bool) DB::transaction(function () use ($order, $courier) {
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedOrder || ! $lockedOrder->canBeStartedBy($courier)) {
                return false;
            }

            if ($lockedOrder->isDispatchDeferred()) {
                Log::info('order_start_blocked_dispatch_deferred', [
                    'order_id' => (int) $lockedOrder->id,
                    'courier_id' => (int) $courier->id,
                    'dispatch_available_at' => $lockedOrder->dispatch_available_at?->toIso8601String(),
                    'now' => now()->toIso8601String(),
                ]);
                return false;
            }
            $this->lifecyclePolicy->assertTransition($lockedOrder->status, Order::STATUS_IN_PROGRESS);

            $lockedOrder->forceFill([
                'status' => Order::STATUS_IN_PROGRESS,
                'started_at' => now(),
            ])->save();

            $courier->markDelivering();

            return true;
        });
    }
}
