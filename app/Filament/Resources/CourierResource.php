<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CourierResource\Pages;
use App\Models\Courier;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CourierResource extends Resource
{
    protected static ?string $model = Courier::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Courier';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('user_id')
                ->label('Courier user')
                ->relationship(
                    name: 'user',
                    titleAttribute: 'email',
                    modifyQueryUsing: fn ($query) => $query
                        ->where('role', 'courier')
                        ->whereDoesntHave('courier')
                )
                ->searchable()
                ->required()
                ->disabled(fn (?Courier $record) => $record !== null)
                ->rules(fn (?Courier $record): array => $record
                    ? []
                    : ['unique:couriers,user_id']),

            Section::make('Courier profile')
                ->relationship('user')
                ->schema([
                    TextInput::make('name')
                        ->label('Full name')
                        ->maxLength(255)
                        ->required(),
                    TextInput::make('email')
                        ->email()
                        ->maxLength(255)
                        ->required(),
                    TextInput::make('phone')
                        ->tel()
                        ->maxLength(255),
                    TextInput::make('residence_address')
                        ->label('Address')
                        ->maxLength(500),
                ])
                ->columns(2)
                ->visible(fn (?Courier $record): bool => $record !== null),

            Select::make('status')
                ->options([
                    Courier::STATUS_OFFLINE => 'Offline',
                    Courier::STATUS_ONLINE => 'Online',
                    Courier::STATUS_ASSIGNED => 'Assigned',
                    Courier::STATUS_DELIVERING => 'Delivering',
                ])
                ->default(Courier::STATUS_OFFLINE)
                ->disabled(fn (?Courier $record) => $record !== null)
                ->dehydrated(fn (?Courier $record) => $record === null)
                ->required(),

            TextInput::make('city')
                ->maxLength(255),

            Select::make('transport')
                ->options([
                    'walk' => 'Walk',
                    'bike' => 'Bike',
                    'car' => 'Car',
                ])
                ->default('walk')
                ->required(),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'online',
                        'warning' => 'busy',
                        'gray' => 'offline',
                    ]),

                TextColumn::make('city')
                    ->sortable(),

                TextColumn::make('transport')
                    ->badge(),

                IconColumn::make('is_verified')
                    ->boolean(),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Tables\Actions\Action::make('verification_requests')
                    ->label('Verification requests')
                    ->icon('heroicon-o-identification')
                    ->url(fn (Courier $record): string => CourierVerificationRequestResource::getUrl('index', [
                        'tableFilters' => [
                            'courier_id' => ['value' => $record->user_id],
                        ],
                    ])),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCouriers::route('/'),
            'create' => Pages\CreateCourier::route('/create'),
            'edit' => Pages\EditCourier::route('/{record}/edit'),
        ];
    }
}
