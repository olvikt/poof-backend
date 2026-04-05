<?php

namespace App\Filament\Widgets;

use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PoofStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        return [
            Stat::make(
                'Курьеры онлайн',
                Courier::query()->whereIn('status', [
                    Courier::STATUS_ONLINE,
                    Courier::STATUS_ASSIGNED,
                    Courier::STATUS_DELIVERING,
                ])->count(),
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
                Order::query()->whereIn('status', [
                    Order::STATUS_SEARCHING,
                    Order::STATUS_ACCEPTED,
                    Order::STATUS_IN_PROGRESS,
                ])->count(),
            )->color('warning'),

            Stat::make(
                'Заказы сегодня',
                Order::query()
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->count(),
            )->color('info'),

            Stat::make(
                'Доход сегодня (завершено)',
                '₴' . Order::query()
                    ->where('status', Order::STATUS_DONE)
                    ->where('payment_status', Order::PAY_PAID)
                    ->whereBetween('completed_at', [$todayStart, $todayEnd])
                    ->sum('price'),
            )->color('success'),

            Stat::make(
                'Всего клиентов',
                User::query()->where('role', User::ROLE_CLIENT)->count(),
            )->color('gray'),

            Stat::make(
                'Всего курьеров',
                User::query()->where('role', User::ROLE_COURIER)->count(),
            )->color('gray'),
        ];
    }
}
