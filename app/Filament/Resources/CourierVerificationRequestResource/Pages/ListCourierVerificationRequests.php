<?php

declare(strict_types=1);

namespace App\Filament\Resources\CourierVerificationRequestResource\Pages;

use App\Filament\Resources\CourierVerificationRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListCourierVerificationRequests extends ListRecords
{
    protected static string $resource = CourierVerificationRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
