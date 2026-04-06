<?php

declare(strict_types=1);

namespace App\Services\Dispatch;

use App\Models\OrderOffer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class PendingOfferSweeper
{
    public function run(int $limit = 200, ?Carbon $now = null): int
    {
        $now ??= now();
        $limit = max(1, $limit);

        $ids = OrderOffer::query()
            ->where('status', OrderOffer::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->orderBy('expires_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        if ($ids->isEmpty()) {
            Log::info('pending_offers_expired_batch', [
                'expired_count' => 0,
                'batch_limit' => $limit,
                'counter' => 'pending_offers_expired_total',
                'counter_increment' => 0,
            ]);

            return 0;
        }

        $expiredCount = OrderOffer::query()
            ->whereIn('id', $ids)
            ->where('status', OrderOffer::STATUS_PENDING)
            ->update(['status' => OrderOffer::STATUS_EXPIRED]);

        Log::info('pending_offers_expired_batch', [
            'expired_count' => $expiredCount,
            'batch_limit' => $limit,
            'counter' => 'pending_offers_expired_total',
            'counter_increment' => $expiredCount,
        ]);

        return $expiredCount;
    }
}
