<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets;
use App\Filament\Widgets\OrdersMap;

class Dashboard extends BaseDashboard
{
    /**
     * ✅ Одна колонка = full width
     */
    public function getColumns(): int | string | array
    {
        return 1;
    }

    /**
     * 🔝 Header widgets (САМОЕ ВЕРХНЕЕ)
     */
    public function getHeaderWidgets(): array
    {
        return [
            Widgets\AccountWidget::class,
        ];
    }

    /**
     * 🧱 Основной контент страницы
     * Welcome → КАРТА → Filament info
     */
    public function getWidgets(): array
    {
        return [
            OrdersMap::class,
            Widgets\FilamentInfoWidget::class,
        ];
    }
}


