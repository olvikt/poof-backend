<?php

namespace App\Filament\Resources\CourierResource\Pages;

use App\Filament\Resources\CourierResource;
use App\Filament\Resources\CourierVerificationRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCourier extends EditRecord
{
    protected static string $resource = CourierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('verification_requests')
                ->label('Verification requests')
                ->icon('heroicon-o-identification')
                ->url(fn (): string => CourierVerificationRequestResource::getUrl('index', [
                    'tableFilters' => [
                        'courier_id' => ['value' => $this->record->user_id],
                    ],
                ])),
            Actions\DeleteAction::make(),
        ];
    }
}
