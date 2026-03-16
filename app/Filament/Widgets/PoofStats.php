<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Courier;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PoofStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        return [
            Stat::make(
                'Курьеры онлайн',
                Courier::activeOnMap()->count(),
            )->color('success'),

            Stat::make(
                'Свободные курьеры',
                Courier::available()->count(),
            )->color('success'),

            Stat::make(
                'Занятые курьеры',
                Courier::busy()->count(),
            )->color('primary'),

            Stat::make(
                'Активные заказы',
                Order::whereIn('status', [
                    'new',
                    'searching',
                    'accepted',
                    'in_progress',
                ])->count(),
            )->color('warning'),

            Stat::make(
                'Заказы сегодня',
                Order::whereDate('created_at', today())->count(),
            )->color('info'),

            Stat::make(
                'Доход сегодня',
                '₴' . Order::whereDate('created_at', today())->sum('price'),
            )->color('success'),
        ];
    }
}
