<?php

declare(strict_types=1);

namespace App\Filament\Resources\CourierEarningSettingResource\Pages;

use App\Filament\Resources\CourierEarningSettingResource;
use App\Models\CourierEarningSetting;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCourierEarningSettings extends ListRecords
{
    protected static string $resource = CourierEarningSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn (): bool => CourierEarningSetting::query()->count() === 0),
        ];
    }
}
