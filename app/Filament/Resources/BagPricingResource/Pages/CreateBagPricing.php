<?php

declare(strict_types=1);

namespace App\Filament\Resources\BagPricingResource\Pages;

use App\Filament\Resources\BagPricingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBagPricing extends CreateRecord
{
    protected static string $resource = BagPricingResource::class;
}
