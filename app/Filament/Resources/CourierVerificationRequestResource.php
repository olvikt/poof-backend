<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Actions\Courier\Verification\ApproveCourierVerificationRequestAction;
use App\Actions\Courier\Verification\RejectCourierVerificationRequestAction;
use App\Filament\Resources\CourierVerificationRequestResource\Pages;
use App\Models\CourierVerificationRequest;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CourierVerificationRequestResource extends Resource
{
    protected static ?string $model = CourierVerificationRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Courier verification';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('courier.name')->label('Courier')->searchable(),
                TextColumn::make('document_type')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('submitted_at')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('reviewer.name')->label('Reviewed by')->default('—'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->visible(fn (CourierVerificationRequest $record): bool => $record->isPendingReview())
                    ->action(function (CourierVerificationRequest $record): void {
                        app(ApproveCourierVerificationRequestAction::class)->execute($record, auth()->user());
                        Notification::make()->title('Verification approved')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->visible(fn (CourierVerificationRequest $record): bool => $record->isPendingReview())
                    ->form([
                        Textarea::make('rejection_reason')->label('Rejection reason')->required()->maxLength(500),
                    ])
                    ->action(function (CourierVerificationRequest $record, array $data): void {
                        app(RejectCourierVerificationRequestAction::class)->execute(
                            $record,
                            auth()->user(),
                            (string) $data['rejection_reason'],
                        );
                        Notification::make()->title('Verification rejected')->success()->send();
                    }),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Verification request')->schema([
                TextEntry::make('id'),
                TextEntry::make('courier.name')->label('Courier'),
                TextEntry::make('courier.email')->label('Courier email'),
                TextEntry::make('document_type')->label('Document type'),
                TextEntry::make('status')->badge(),
                TextEntry::make('rejection_reason')->default('—'),
                TextEntry::make('submitted_at')->dateTime('d.m.Y H:i'),
                TextEntry::make('reviewed_at')
                    ->formatStateUsing(function (mixed $state): string {
                        if (blank($state)) {
                            return '—';
                        }

                        if ($state instanceof DateTimeInterface) {
                            return $state->format('d.m.Y H:i');
                        }

                        return Carbon::parse((string) $state)->format('d.m.Y H:i');
                    }),
                TextEntry::make('document_preview')
                    ->state(fn (CourierVerificationRequest $record): string => route('admin.courier-verification-requests.document', $record))
                    ->url(fn (CourierVerificationRequest $record): string => route('admin.courier-verification-requests.document', $record))
                    ->openUrlInNewTab(),
            ])->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourierVerificationRequests::route('/'),
            'view' => Pages\ViewCourierVerificationRequest::route('/{record}'),
        ];
    }
}
