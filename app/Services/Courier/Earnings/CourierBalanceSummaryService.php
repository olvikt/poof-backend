<?php

declare(strict_types=1);

namespace App\Services\Courier\Earnings;

use App\Models\CourierEarning;
use App\Models\User;

class CourierBalanceSummaryService
{
    public function forCourier(User $courier): array
    {
        $totals = CourierEarning::query()
            ->where('courier_id', $courier->id)
            ->where('earning_status', CourierEarning::STATUS_SETTLED)
            ->selectRaw('COUNT(*) as completed_orders_count')
            ->selectRaw('COALESCE(SUM(gross_amount), 0) as gross_total')
            ->selectRaw('COALESCE(SUM(commission_amount), 0) as commission_total')
            ->selectRaw('COALESCE(SUM(net_amount + bonuses_amount + adjustments_amount - penalties_amount), 0) as net_total')
            ->first();

        $netTotal = (int) ($totals?->net_total ?? 0);

        return [
            'completed_orders_count' => (int) ($totals?->completed_orders_count ?? 0),
            'gross_earnings_total' => (int) ($totals?->gross_total ?? 0),
            'platform_commission_total' => (int) ($totals?->commission_total ?? 0),
            'courier_net_balance' => $netTotal,
            'balance_formatted' => $this->formatUah($netTotal),
        ];
    }

    private function formatUah(int $amount): string
    {
        return number_format($amount, 2, ',', ' ') . ' ₴';
    }
}
