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

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationLabel = 'Orders';
    protected static ?string $pluralLabel = 'Orders';

    /* =========================================================
     |  FORM
     | ========================================================= */

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('address')
                ->label('Address')
                ->required()
                ->columnSpanFull(),

            Textarea::make('comment')
                ->label('Comment')
                ->columnSpanFull(),

            Select::make('status')
                ->label('Status')
                ->options(Order::STATUS_LABELS)
                ->required(),

            Select::make('courier_id')
                ->label('Courier')
                ->relationship('courier', 'name')
                ->searchable()
                ->nullable()
                ->disabled(fn ($record) =>
                    $record && $record->status !== Order::STATUS_NEW
                ),

            TextInput::make('price')
                ->numeric()
                ->label('Price')
                ->required(),

            DateTimePicker::make('scheduled_at')
                ->label('Scheduled at'),
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
                        fn (string $state) => Order::STATUS_LABELS[$state] ?? $state
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
			Tables\Actions\EditAction::make(),

			Tables\Actions\Action::make('start')
				->label('Start')
				->icon('heroicon-o-play')
				->color('info')
				->visible(fn (Order $record) => $record->canBeStarted())
				->action(fn (Order $record) => $record->start())
				->requiresConfirmation(),

			Tables\Actions\Action::make('complete')
				->label('Complete')
				->icon('heroicon-o-check')
				->color('success')
				->visible(fn (Order $record) => $record->canBeCompleted())
				->action(fn (Order $record) => $record->complete())
				->requiresConfirmation(),

			Tables\Actions\Action::make('cancel')
				->label('Cancel')
				->icon('heroicon-o-x-mark')
				->color('danger')
				->visible(fn (Order $record) => $record->canBeCancelled())
				->action(fn (Order $record) => $record->cancel())
				->requiresConfirmation(),
		])
            ->defaultSort('created_at', 'desc');
    }

    /* =========================================================
     |  VISIBILITY BY ROLE
     | ========================================================= */

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        if ($user->role === 'admin') {
            return parent::getEloquentQuery();
        }

        if ($user->role === 'courier') {
            return parent::getEloquentQuery()
                ->where('courier_id', $user->id);
        }

        return parent::getEloquentQuery()
            ->where('client_id', $user->id);
    }

    /* =========================================================
     |  PAGES
     | ========================================================= */

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit'   => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}

