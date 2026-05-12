<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CourierOrderInterest;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Services\Dispatch\OfferDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class FinalizeScheduledOrderMatchingCommand extends Command
{
    protected $signature = 'courier:finalize-scheduled-order-matching {--minutes=30} {--limit=100}';
    protected $description = 'Finalize matching for scheduled searching orders close to preferred window start.';

    public function handle(OfferDispatcher $dispatcher): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $limit = max(1, (int) $this->option('limit'));
        $now = now();
        $target = $now->copy()->addMinutes($minutes);

        $orders = Order::query()
            ->where('status', Order::STATUS_SEARCHING)
            ->where('payment_status', Order::PAY_PAID)
            ->whereNull('courier_id')
            ->whereNull('expired_at')
            ->where('window_from_at', '<=', $target)
            ->where('window_from_at', '>=', $now)
            ->limit($limit)
            ->get();

        foreach ($orders as $order) {
            $lock = Cache::lock("finalize:scheduled:{$order->id}", 20);
            if (! $lock->get()) {
                continue;
            }

            try {
                $hasAlive = OrderOffer::query()->where('order_id', $order->id)->alive()->exists();
                if ($hasAlive) {
                    continue;
                }

                $interest = CourierOrderInterest::query()
                    ->where('order_id', $order->id)
                    ->where('status', CourierOrderInterest::STATUS_INTERESTED)
                    ->orderBy('distance_meters')
                    ->orderBy('eta_seconds')
                    ->orderBy('expressed_at')
                    ->first();

                if ($interest && $interest->courier?->canAcceptOrders()) {
                    OrderOffer::createPrimaryPending((int) $order->id, (int) $interest->courier_id, 45);
                    $interest->update(['status' => CourierOrderInterest::STATUS_SELECTED, 'selected_at' => now()]);
                } else {
                    $dispatcher->dispatchForOrder($order, 'scheduled_final_matching_fallback');
                }
            } finally {
                optional($lock)->release();
            }
        }

        return self::SUCCESS;
    }
}

