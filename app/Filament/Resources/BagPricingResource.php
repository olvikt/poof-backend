<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\BagPricingResource\Pages;
use App\Models\BagPricing;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BagPricingResource extends Resource
{
    protected static ?string $model = BagPricing::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = 'Тарифи мішків';
    protected static ?string $pluralLabel = 'Тарифи мішків';

    public static function canViewAny(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('bags_count')
                ->label('Кількість мішків')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->required()
                ->unique(ignoreRecord: true),

            TextInput::make('price')
                ->label('Ціна (грн)')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->required(),

            Toggle::make('is_active')
                ->label('Активний')
                ->default(true)
                ->required(),

            TextInput::make('sort_order')
                ->label('Порядок')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bags_count')
                    ->label('Мішків')
                    ->sortable(),

                TextColumn::make('price')
                    ->label('Ціна')
                    ->suffix(' грн')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Активний')
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBagPricings::route('/'),
            'create' => Pages\CreateBagPricing::route('/create'),
            'edit' => Pages\EditBagPricing::route('/{record}/edit'),
        ];
    }
}
