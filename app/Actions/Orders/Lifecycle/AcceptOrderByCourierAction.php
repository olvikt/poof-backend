<?php

declare(strict_types=1);

namespace App\Actions\Orders\Lifecycle;

use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcceptOrderByCourierAction
{
    /**
     * Прийняти замовлення курʼєром (атомарно)
     */
    public function handle(Order $order, User $courier): bool
    {
        return (bool) DB::transaction(function () use ($order, $courier) {
            $courier = User::query()
                ->whereKey($courier->getKey())
                ->lockForUpdate()
                ->first();

            if (! $courier || ! $courier->isCourier()) {
                return false;
            }

            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedOrder || ! $lockedOrder->canBeAccepted()) {
                return false;
            }

            if ($courier->isBusyForAccept() || ! $courier->canAcceptOrders()) {
                return false;
            }

            $lockedOrder->update([
                'status' => Order::STATUS_ACCEPTED,
                'courier_id' => $courier->id,
                'accepted_at' => now(),
            ]);

            $courier->markBusy();

            OrderOffer::where('courier_id', $courier->id)
                ->where('status', OrderOffer::STATUS_PENDING)
                ->where('order_id', '!=', $lockedOrder->id)
                ->update([
                    'status' => OrderOffer::STATUS_EXPIRED,
                ]);

            return true;
        });
    }
}
