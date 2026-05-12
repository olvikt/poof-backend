<?php

declare(strict_types=1);

namespace App\Actions\Orders\Lifecycle;

use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\CourierOrderInterest;
use App\Models\User;
use App\Support\Orders\OrderLifecycleTransitionPolicy;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AcceptOrderByCourierAction
{
    public const OFFER_ACCEPT_SUCCESS = 'success';
    public const OFFER_ACCEPT_ALREADY_ASSIGNED_TO_SAME_COURIER = 'already_assigned_to_same_courier';
    public const OFFER_ACCEPT_REJECTED = 'rejected';

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
        return $this->handleOfferWithResult($offer, $courier) === self::OFFER_ACCEPT_SUCCESS;
    }

    public function handleOfferWithResult(OrderOffer $offer, User $courier): string
    {
        Log::info('order_offer_accept_started', [
            'order_id' => $offer->order_id,
            'offer_id' => $offer->id,
            'courier_id' => $courier->id,
            'lock_acquired' => false,
        ]);

        return DB::transaction(function () use ($offer, $courier) {
            $courier = User::query()->whereKey($courier->getKey())->lockForUpdate()->first();

            if (! $courier || ! $courier->isCourier()) {
                Log::info('order_offer_accept_rejected', ['order_id' => $offer->order_id, 'offer_id' => $offer->id, 'courier_id' => $courier?->id, 'reason' => 'courier_not_found_or_invalid', 'lock_acquired' => false, 'competing_offers_expired_count' => 0, 'competing_interests_rejected_count' => 0]);
                return self::OFFER_ACCEPT_REJECTED;
            }

            $lockedOffer = OrderOffer::query()->whereKey($offer->getKey())->lockForUpdate()->first();
            if (! $lockedOffer || (int) $lockedOffer->courier_id !== (int) $courier->getKey()) {
                Log::info('order_offer_accept_rejected', ['order_id' => $offer->order_id, 'offer_id' => $offer->id, 'courier_id' => $courier->id, 'reason' => 'offer_not_found_or_wrong_owner', 'lock_acquired' => true, 'competing_offers_expired_count' => 0, 'competing_interests_rejected_count' => 0]);
                return self::OFFER_ACCEPT_REJECTED;
            }

            if ($lockedOffer->status !== OrderOffer::STATUS_PENDING || ! $lockedOffer->isAlive()) {
                Log::info('order_offer_accept_rejected', ['order_id' => $offer->order_id, 'offer_id' => $offer->id, 'courier_id' => $courier->id, 'reason' => 'offer_not_pending_or_expired', 'lock_acquired' => true, 'competing_offers_expired_count' => 0, 'competing_interests_rejected_count' => 0]);
                return self::OFFER_ACCEPT_REJECTED;
            }

            $lockedOrder = Order::query()->whereKey($lockedOffer->order_id)->lockForUpdate()->first();
            if (! $lockedOrder || $lockedOrder->status !== Order::STATUS_SEARCHING || $lockedOrder->courier_id !== null) {
                $assignedToSameCourier = $lockedOrder && (int) $lockedOrder->courier_id === (int) $courier->id && $lockedOrder->status === Order::STATUS_ACCEPTED;
                if ($assignedToSameCourier) {
                    Log::info('order_offer_accept_succeeded', ['order_id' => $lockedOrder->id, 'offer_id' => $lockedOffer->id, 'courier_id' => $courier->id, 'reason' => 'idempotent_already_assigned', 'lock_acquired' => true, 'competing_offers_expired_count' => 0, 'competing_interests_rejected_count' => 0]);
                    return self::OFFER_ACCEPT_ALREADY_ASSIGNED_TO_SAME_COURIER;
                }

                Log::info('order_offer_accept_rejected', ['order_id' => $offer->order_id, 'offer_id' => $offer->id, 'courier_id' => $courier->id, 'reason' => 'order_not_searching_or_already_assigned', 'lock_acquired' => true, 'competing_offers_expired_count' => 0, 'competing_interests_rejected_count' => 0]);
                return self::OFFER_ACCEPT_REJECTED;
            }

            if ($courier->isBusyForAccept() || ! $courier->canAcceptOrders()) {
                Log::info('order_offer_accept_rejected', ['order_id' => $lockedOrder->id, 'offer_id' => $lockedOffer->id, 'courier_id' => $courier->id, 'reason' => 'courier_busy_or_not_eligible', 'lock_acquired' => true, 'competing_offers_expired_count' => 0, 'competing_interests_rejected_count' => 0]);
                return self::OFFER_ACCEPT_REJECTED;
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
                Log::info('order_offer_accept_race_lost', ['order_id' => $lockedOrder->id, 'offer_id' => $lockedOffer->id, 'courier_id' => $courier->id, 'reason' => 'conditional_order_update_failed', 'lock_acquired' => true, 'competing_offers_expired_count' => 0, 'competing_interests_rejected_count' => 0]);
                return self::OFFER_ACCEPT_REJECTED;
            }

            OrderOffer::query()
                ->whereKey($lockedOffer->getKey())
                ->where('status', OrderOffer::STATUS_PENDING)
                ->update(['status' => OrderOffer::STATUS_ACCEPTED]);

            $competingOffersExpired = OrderOffer::query()
                ->where('order_id', $lockedOrder->id)
                ->where('id', '!=', $lockedOffer->id)
                ->where('status', OrderOffer::STATUS_PENDING)
                ->update(['status' => OrderOffer::STATUS_EXPIRED]);

            $competingInterestsRejected = CourierOrderInterest::query()
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

            Log::info('order_offer_accept_succeeded', ['order_id' => $lockedOrder->id, 'offer_id' => $lockedOffer->id, 'courier_id' => $courier->id, 'reason' => null, 'lock_acquired' => true, 'competing_offers_expired_count' => $competingOffersExpired, 'competing_interests_rejected_count' => $competingInterestsRejected]);
            return self::OFFER_ACCEPT_SUCCESS;
        });
    }
}
