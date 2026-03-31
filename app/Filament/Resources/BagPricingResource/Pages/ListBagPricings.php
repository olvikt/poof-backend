<?php

declare(strict_types=1);

namespace App\Filament\Resources\BagPricingResource\Pages;

use App\Filament\Resources\BagPricingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBagPricings extends ListRecords
{
    protected static string $resource = BagPricingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
