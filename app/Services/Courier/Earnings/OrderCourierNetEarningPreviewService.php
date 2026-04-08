<?php

declare(strict_types=1);

namespace App\Services\Courier\Earnings;

use App\Models\Order;

class OrderCourierNetEarningPreviewService
{
    public function __construct(
        private readonly CourierCommissionResolver $commissionResolver,
    ) {
    }

    /**
     * @return array{gross_amount:int,commission_rate_percent:float,commission_amount:int,net_amount:int,formatted:string}
     */
    public function forOrder(Order $order): array
    {
        // Keep preview semantics aligned with settled ledger formula:
        // gross = order.price, commission = global setting snapshot at render-time.
        $grossAmount = max(0, (int) $order->price);
        $commissionRate = $this->commissionResolver->globalRatePercent();
        $commissionAmount = (int) round($grossAmount * $commissionRate / 100, 0, PHP_ROUND_HALF_UP);
        $netAmount = max(0, $grossAmount - $commissionAmount);

        return [
            'gross_amount' => $grossAmount,
            'commission_rate_percent' => $commissionRate,
            'commission_amount' => $commissionAmount,
            'net_amount' => $netAmount,
            'formatted' => $netAmount.' грн.',
        ];
    }
}
