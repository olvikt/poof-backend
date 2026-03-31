<?php

declare(strict_types=1);

namespace App\Filament\Resources\BagPricingResource\Pages;

use App\Filament\Resources\BagPricingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBagPricing extends EditRecord
{
    protected static string $resource = BagPricingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
