<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PoofStats extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make(
                'Курьеры онлайн',
                User::where('role', 'courier')
                    ->where('is_online', 1)
                    ->count(),
            )
                ->color('success'),

            Stat::make(
                'Активные заказы',
                Order::whereIn('status', [
                    'new',
                    'searching',
                    'accepted',
                    'in_progress',
                ])->count(),
            )
                ->color('warning'),

            Stat::make(
                'Заказы сегодня',
                Order::whereDate('created_at', today())->count(),
            )
                ->color('info'),

            Stat::make(
                'Доход сегодня',
                '₴' . Order::whereDate('created_at', today())->sum('price'),
            )
                ->color('success'),
        ];
    }
}
