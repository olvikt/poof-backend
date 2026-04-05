<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderOffer;
use App\Support\Orders\OrderPromiseResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderAutoExpireService
{
    public function __construct(private readonly OrderPromiseResolver $promiseResolver)
    {
    }

    public function run(int $limit = 200, ?Carbon $now = null): int
    {
        if (! config('order_promise.auto_expire_enabled', true)) {
            return 0;
        }

        $now ??= now();
        $expiredCount = 0;

        $ids = Order::query()
            ->where('status', Order::STATUS_SEARCHING)
            ->where('payment_status', Order::PAY_PAID)
            ->whereNull('courier_id')
            ->whereNull('expired_at')
            ->whereNotNull('valid_until_at')
            ->where('valid_until_at', '<=', $now)
            ->orderBy('valid_until_at')
            ->limit($limit)
            ->pluck('id');

        foreach ($ids as $id) {
            if ($this->expireOne((int) $id, $now)) {
                $expiredCount++;
            }
        }

        return $expiredCount;
    }

    public function expireOne(int $orderId, ?Carbon $now = null): bool
    {
        $now ??= now();

        return (bool) DB::transaction(function () use ($orderId, $now): bool {
            /** @var Order|null $order */
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();

            if (! $order) {
                return false;
            }

            if ($order->status !== Order::STATUS_SEARCHING
                || $order->courier_id !== null
                || $order->payment_status !== Order::PAY_PAID
                || $order->expired_at !== null
                || ! $order->valid_until_at
                || $order->valid_until_at->isFuture()) {
                Log::debug('order_expire_skipped', [
                    'order_id' => $order->id,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'has_courier' => $order->courier_id !== null,
                    'expired_at' => optional($order->expired_at)?->toIso8601String(),
                    'valid_until_at' => optional($order->valid_until_at)?->toIso8601String(),
                ]);

                return false;
            }

            $activeOfferCount = OrderOffer::query()
                ->where('order_id', $order->id)
                ->where('status', OrderOffer::STATUS_PENDING)
                ->count();

            OrderOffer::query()
                ->where('order_id', $order->id)
                ->where('status', OrderOffer::STATUS_PENDING)
                ->update(['status' => OrderOffer::STATUS_EXPIRED]);

            $reason = $this->promiseResolver->resolveExpiredReason($order, $now);

            $order->forceFill([
                'status' => Order::STATUS_CANCELLED,
                'expired_at' => $now,
                'expired_reason' => $reason,
                'next_dispatch_at' => null,
            ])->save();

            Log::info('order_expired', [
                'order_id' => $order->id,
                'expired_reason' => $reason,
                'order_age_seconds' => $order->created_at ? $order->created_at->diffInSeconds($now) : null,
                'had_live_pending_offer' => $activeOfferCount > 0,
                'active_offer_count' => $activeOfferCount,
            ]);

            return true;
        });
    }
}
