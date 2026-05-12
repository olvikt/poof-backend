<?php

declare(strict_types=1);

namespace App\Actions\Orders\Lifecycle;

use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\CourierOrderInterest;
use App\Models\User;
use App\Support\Orders\OrderLifecycleTransitionPolicy;
use Illuminate\Support\Facades\DB;

class AcceptOrderByCourierAction
{
    public function __construct(private readonly OrderLifecycleTransitionPolicy $lifecyclePolicy)
    {
    }
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
            $this->lifecyclePolicy->assertTransition($lockedOrder->status, Order::STATUS_ACCEPTED);

            $lockedOrder->forceFill([
                'status' => Order::STATUS_ACCEPTED,
                'courier_id' => $courier->id,
                'accepted_at' => now(),
            ])->save();

            $courier->markBusy();
            $courier->refresh();
            $courier->repairCourierRuntimeState();

            OrderOffer::where('courier_id', $courier->id)
                ->where('status', OrderOffer::STATUS_PENDING)
                ->where('order_id', '!=', $lockedOrder->id)
                ->update([
                    'status' => OrderOffer::STATUS_EXPIRED,
                ]);

            return true;
        });
    }

    public function handleOffer(OrderOffer $offer, User $courier): bool
    {
        return (bool) DB::transaction(function () use ($offer, $courier) {
            $courier = User::query()->whereKey($courier->getKey())->lockForUpdate()->first();

            if (! $courier || ! $courier->isCourier()) {
                return false;
            }

            $lockedOffer = OrderOffer::query()->whereKey($offer->getKey())->lockForUpdate()->first();
            if (! $lockedOffer || (int) $lockedOffer->courier_id !== (int) $courier->getKey()) {
                return false;
            }

            if ($lockedOffer->status !== OrderOffer::STATUS_PENDING || ! $lockedOffer->isAlive()) {
                return false;
            }

            $lockedOrder = Order::query()->whereKey($lockedOffer->order_id)->lockForUpdate()->first();
            if (! $lockedOrder || $lockedOrder->status !== Order::STATUS_SEARCHING || $lockedOrder->courier_id !== null) {
                return false;
            }

            if ($courier->isBusyForAccept() || ! $courier->canAcceptOrders()) {
                return false;
            }

            $this->lifecyclePolicy->assertTransition($lockedOrder->status, Order::STATUS_ACCEPTED);

            $updated = Order::query()
                ->whereKey($lockedOrder->getKey())
                ->where('status', Order::STATUS_SEARCHING)
                ->whereNull('courier_id')
                ->update([
                    'status' => Order::STATUS_ACCEPTED,
                    'courier_id' => $courier->id,
                    'accepted_at' => now(),
                ]);

            if ($updated !== 1) {
                return false;
            }

            OrderOffer::query()
                ->whereKey($lockedOffer->getKey())
                ->where('status', OrderOffer::STATUS_PENDING)
                ->update(['status' => OrderOffer::STATUS_ACCEPTED]);

            OrderOffer::query()
                ->where('order_id', $lockedOrder->id)
                ->where('id', '!=', $lockedOffer->id)
                ->where('status', OrderOffer::STATUS_PENDING)
                ->update(['status' => OrderOffer::STATUS_EXPIRED]);

            CourierOrderInterest::query()
                ->where('order_id', $lockedOrder->id)
                ->where('courier_id', '!=', $courier->id)
                ->whereIn('status', [CourierOrderInterest::STATUS_INTERESTED, CourierOrderInterest::STATUS_SELECTED])
                ->update([
                    'status' => CourierOrderInterest::STATUS_REJECTED,
                    'rejected_reason' => 'selected_elsewhere',
                ]);

            CourierOrderInterest::query()
                ->where('order_id', $lockedOrder->id)
                ->where('courier_id', $courier->id)
                ->whereIn('status', [CourierOrderInterest::STATUS_INTERESTED, CourierOrderInterest::STATUS_REJECTED])
                ->update([
                    'status' => CourierOrderInterest::STATUS_SELECTED,
                    'selected_at' => now(),
                    'rejected_reason' => null,
                ]);

            $courier->markBusy();
            $courier->refresh();
            $courier->repairCourierRuntimeState();

            OrderOffer::where('courier_id', $courier->id)
                ->where('status', OrderOffer::STATUS_PENDING)
                ->where('order_id', '!=', $lockedOrder->id)
                ->update(['status' => OrderOffer::STATUS_EXPIRED]);

            return true;
        });
    }
}
