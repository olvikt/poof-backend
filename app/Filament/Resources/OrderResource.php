<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;

use Illuminate\Database\Eloquent\Builder;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon  = 'heroicon-o-shopping-bag';
    protected static ?string $navigationLabel = 'Orders';
    protected static ?string $pluralLabel     = 'Orders';

    /* =========================================================
     |  CREATE PERMISSIONS
     | ========================================================= */

    /**
     * ❌ Админ НЕ создаёт заказы
     * ✅ Только клиент
     */
    public static function canCreate(): bool
    {
        return auth()->user()?->role === 'client';
    }

    /* =========================================================
     |  FORM
     | ========================================================= */

    public static function form(Form $form): Form
    {
        return $form->schema([

            /* ---------------- ADDRESS ---------------- */

            TextInput::make('address')
                ->label('Address')
                ->required()
                ->columnSpanFull()
                ->disabled(fn ($record) =>
                    $record && auth()->user()->role !== 'client'
                ),

            /* ---------------- COMMENT ---------------- */

            Textarea::make('comment')
                ->label('Comment')
                ->columnSpanFull()
                ->disabled(fn ($record) =>
                    $record && auth()->user()->role !== 'client'
                ),

            /* ---------------- STATUS ---------------- */

            Select::make('status')
                ->label('Status')
                ->options(Order::STATUS_LABELS)
                ->required()
                ->disabled(fn () =>
                    auth()->user()->role === 'client'
                ),

            /* ---------------- COURIER ---------------- */

            Select::make('courier_id')
                ->label('Courier')
                ->relationship('courier', 'name')
                ->searchable()
                ->nullable()
                ->visible(fn () =>
                    auth()->user()->role === 'admin'
                ),

            /* ---------------- PRICE ---------------- */

            TextInput::make('price')
                ->label('Price')
                ->numeric()
                ->required()
                ->disabled(fn ($record) =>
                    $record && auth()->user()->role !== 'client'
                ),

            /* ---------------- SCHEDULE ---------------- */

            DateTimePicker::make('scheduled_at')
                ->label('Scheduled at')
                ->disabled(fn ($record) =>
                    $record && auth()->user()->role !== 'client'
                ),
        ]);
    }

    /* =========================================================
     |  TABLE
     | ========================================================= */

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('client.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('courier.name')
                    ->label('Courier')
                    ->default('—')
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'primary' => Order::STATUS_NEW,
                        'warning' => Order::STATUS_ACCEPTED,
                        'info'    => Order::STATUS_IN_PROGRESS,
                        'success' => Order::STATUS_DONE,
                        'danger'  => Order::STATUS_CANCELLED,
                    ])
                    ->formatStateUsing(
                        fn (string $state) =>
                            Order::STATUS_LABELS[$state] ?? $state
                    )
                    ->sortable(),

                TextColumn::make('price')
                    ->label('Price')
                    ->money('UAH')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(Order::STATUS_LABELS),
            ])
            ->actions([

                /* ---------- EDIT ---------- */

                Tables\Actions\EditAction::make(),

                /* ---------- START ---------- */

                Tables\Actions\Action::make('start')
                    ->label('Start')
                    ->icon('heroicon-o-play')
                    ->color('info')
                    ->visible(fn (Order $record) =>
                        $record->canBeStarted()
                    )
                    ->action(fn (Order $record) =>
                        $record->start()
                    )
                    ->requiresConfirmation(),

                /* ---------- COMPLETE ---------- */

                Tables\Actions\Action::make('complete')
                    ->label('Complete')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Order $record) =>
                        $record->canBeCompleted()
                    )
                    ->action(fn (Order $record) =>
                        $record->complete()
                    )
                    ->requiresConfirmation(),

                /* ---------- CANCEL ---------- */

                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (Order $record) =>
                        $record->canBeCancelled()
                    )
                    ->action(fn (Order $record) =>
                        $record->cancel()
                    )
                    ->requiresConfirmation(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /* =========================================================
     |  DATA VISIBILITY BY ROLE
     | ========================================================= */

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return match ($user->role) {
            'admin'   => parent::getEloquentQuery(),
            'courier' => parent::getEloquentQuery()
                ->where('courier_id', $user->id),
            default   => parent::getEloquentQuery()
                ->where('client_id', $user->id),
        };
    }

    /* =========================================================
     |  PAGES
     | ========================================================= */

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'edit'  => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}

