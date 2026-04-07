<?php

declare(strict_types=1);

namespace App\Services\Courier\Earnings;

use App\Models\CourierEarning;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CourierCompletedOrdersDailyStatsQuery
{
    /**
     * @return Collection<int, array{date:string,label:string,total_amount:int,total_amount_formatted:string,orders:array<int, array{order_id:int,address_text:string,completed_at:string,completed_time:string,amount:int,amount_formatted:string}>}>
     */
    public function forCourier(User $courier, ?int $days = null): Collection
    {
        $windowDays = max(1, (int) ($days ?? config('courier_runtime.completed_stats.days', 14)));
        $since = now()->startOfDay()->subDays($windowDays - 1);

        $rows = CourierEarning::query()
            ->where('courier_earnings.courier_id', $courier->id)
            ->where('courier_earnings.earning_status', CourierEarning::STATUS_SETTLED)
            ->whereNotNull('courier_earnings.settled_at')
            ->where('courier_earnings.settled_at', '>=', $since)
            ->join('orders', 'orders.id', '=', 'courier_earnings.order_id')
            ->orderByDesc('courier_earnings.settled_at')
            ->get([
                'courier_earnings.order_id',
                'orders.address_text',
                'courier_earnings.settled_at as completed_at',
                'courier_earnings.net_amount',
                'courier_earnings.bonuses_amount',
                'courier_earnings.adjustments_amount',
                'courier_earnings.penalties_amount',
            ]);

        return $rows
            ->groupBy(fn ($row) => CarbonImmutable::parse($row->completed_at)->toDateString())
            ->map(function (Collection $dayRows, string $date): array {
                $day = CarbonImmutable::parse($date);
                $items = $dayRows
                    ->map(function ($row): array {
                        $amount = (int) $row->net_amount + (int) $row->bonuses_amount + (int) $row->adjustments_amount - (int) $row->penalties_amount;
                        $completedAt = CarbonImmutable::parse($row->completed_at);

                        return [
                            'order_id' => (int) $row->order_id,
                            'address_text' => (string) ($row->address_text ?: 'Адреса не вказана'),
                            'completed_at' => $completedAt->toIso8601String(),
                            'completed_time' => $completedAt->format('H:i'),
                            'amount' => $amount,
                            'amount_formatted' => $this->formatUah($amount),
                        ];
                    })
                    ->values()
                    ->all();

                $total = array_sum(array_column($items, 'amount'));

                return [
                    'date' => $date,
                    'label' => $day->isSameDay(now()) ? 'Сьогодні' : $day->format('d.m.Y'),
                    'total_amount' => $total,
                    'total_amount_formatted' => $this->formatUah($total),
                    'orders' => $items,
                ];
            })
            ->values();
    }

    private function formatUah(int $amount): string
    {
        return number_format($amount, 0, ',', ' ') . ' ₴';
    }
}
