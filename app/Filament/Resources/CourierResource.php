<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CourierResource\Pages;
use App\Filament\Resources\CourierResource\RelationManagers;
use App\Models\Courier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CourierResource extends Resource
{
    protected static ?string $model = Courier::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

	public static function form(Form $form): Form
{
    return $form->schema([
        Select::make('user_id')
            ->label('Courier User')
            ->relationship(
                name: 'user',
                titleAttribute: 'email',
                modifyQueryUsing: fn ($query) =>
                    $query
                        ->where('role', 'courier')
                        ->whereDoesntHave('courier')
            )
            ->searchable()
            ->required()
            ->disabled(fn (?Courier $record) => $record !== null)
            ->rules(fn (?Courier $record) => $record
                ? [] // edit → не валидируем unique
                : ['unique:couriers,user_id'] // create → валидируем
            ),

        Select::make('status')
            ->options([
                'offline' => 'Offline',
                'online'  => 'Online',
                'busy'    => 'Busy',
            ])
            ->default('offline')
            ->required(),

        TextInput::make('city')
            ->maxLength(255),

        Select::make('transport')
            ->options([
                'walk' => 'Walk',
                'bike' => 'Bike',
                'car'  => 'Car',
            ])
            ->default('walk')
            ->required(),

        Toggle::make('is_verified')
            ->label('Verified'),
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
            Tables\Actions\EditAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
        ]);
}

    public static function getRelations(): array
    {
        return [
            //
        ];
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
