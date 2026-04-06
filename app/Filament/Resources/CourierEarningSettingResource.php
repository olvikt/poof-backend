<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CourierEarningSettingResource\Pages;
use App\Models\CourierEarningSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CourierEarningSettingResource extends Resource
{
    protected static ?string $model = CourierEarningSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Courier';

    protected static ?string $navigationLabel = 'Courier Earnings Settings';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('global_commission_rate_percent')
                    ->label('Platform commission, %')
                    ->numeric()
                    ->step(0.01)
                    ->minValue(0)
                    ->maxValue(100)
                    ->required()
                    ->helperText('Global platform commission deducted from courier earnings for each settled order.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('global_commission_rate_percent')
                    ->label('Commission %')
                    ->numeric(decimalPlaces: 2),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourierEarningSettings::route('/'),
            'create' => Pages\CreateCourierEarningSetting::route('/create'),
            'edit' => Pages\EditCourierEarningSetting::route('/{record}/edit'),
        ];
    }
}
