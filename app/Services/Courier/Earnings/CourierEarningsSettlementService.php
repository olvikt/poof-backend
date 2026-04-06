<?php

declare(strict_types=1);

namespace App\Services\Courier\Earnings;

use App\Models\CourierEarning;
use App\Models\Order;

class CourierEarningsSettlementService
{
    public function __construct(
        private readonly CourierCommissionResolver $commissionResolver,
    ) {
    }

    public function settleForOrder(Order $order): ?CourierEarning
    {
        if (! $this->shouldSettle($order)) {
            return null;
        }

        $existing = CourierEarning::query()->where('order_id', $order->id)->first();

        if ($existing) {
            return $existing;
        }

        $grossAmount = max(0, (int) $order->price);
        $commissionRate = $this->commissionResolver->globalRatePercent();
        $commissionAmount = (int) round($grossAmount * $commissionRate / 100, 0, PHP_ROUND_HALF_UP);
        $netAmount = max(0, $grossAmount - $commissionAmount);

        return CourierEarning::query()->create([
            'courier_id' => $order->courier_id,
            'order_id' => $order->id,
            'gross_amount' => $grossAmount,
            'commission_rate_percent' => $commissionRate,
            'commission_amount' => $commissionAmount,
            'net_amount' => $netAmount,
            'bonuses_amount' => 0,
            'penalties_amount' => 0,
            'adjustments_amount' => 0,
            'earning_status' => CourierEarning::STATUS_SETTLED,
            'settled_at' => $order->completed_at ?? now(),
        ]);
    }

    private function shouldSettle(Order $order): bool
    {
        return $order->courier_id !== null
            && $order->status === Order::STATUS_DONE
            && $order->payment_status === Order::PAY_PAID
            && $order->completed_at !== null;
    }
}
