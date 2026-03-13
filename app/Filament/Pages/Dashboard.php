<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets;
use App\Filament\Widgets\OrdersMap;
use App\Filament\Widgets\PoofStats;

class Dashboard extends BaseDashboard
{
    public function getColumns(): int | string | array
    {
        return 1;
    }

    public function getHeaderWidgets(): array
    {
        return [
            Widgets\AccountWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            PoofStats::class,
            OrdersMap::class,
        ];
    }
}
