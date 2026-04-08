<?php

declare(strict_types=1);

namespace App\Filament\Resources\CourierVerificationRequestResource\Pages;

use App\Actions\Courier\Verification\ApproveCourierVerificationRequestAction;
use App\Actions\Courier\Verification\RejectCourierVerificationRequestAction;
use App\Filament\Resources\CourierVerificationRequestResource;
use App\Models\CourierVerificationRequest;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewCourierVerificationRequest extends ViewRecord
{
    protected static string $resource = CourierVerificationRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label('Approve')
                ->color('success')
                ->visible(fn (): bool => $this->record instanceof CourierVerificationRequest && $this->record->isPendingReview())
                ->action(function (): void {
                    app(ApproveCourierVerificationRequestAction::class)->execute($this->record, auth()->user());
                    Notification::make()->title('Verification approved')->success()->send();
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                }),
            Actions\Action::make('reject')
                ->label('Reject')
                ->color('danger')
                ->visible(fn (): bool => $this->record instanceof CourierVerificationRequest && $this->record->isPendingReview())
                ->form([
                    Textarea::make('rejection_reason')->required()->maxLength(500),
                ])
                ->action(function (array $data): void {
                    app(RejectCourierVerificationRequestAction::class)->execute($this->record, auth()->user(), (string) $data['rejection_reason']);
                    Notification::make()->title('Verification rejected')->success()->send();
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                }),
        ];
    }
}
