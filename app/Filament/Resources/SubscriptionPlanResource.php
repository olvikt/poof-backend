<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionPlanResource\Pages;
use App\Models\SubscriptionPlan;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionPlanResource extends Resource
{
    protected static ?string $model = SubscriptionPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';
    protected static ?string $navigationLabel = 'Плани підписки';
    protected static ?string $pluralLabel = 'Плани підписки';

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
            TextInput::make('name')->label('Назва')->required()->maxLength(255),
            TextInput::make('slug')->label('Slug')->required()->unique(ignoreRecord: true)->maxLength(255),
            TextInput::make('frequency_type')->label('Тип частоти')->required()->maxLength(32),
            TextInput::make('pickups_per_month')->label('Виносів / місяць')->numeric()->integer()->minValue(1)->required(),
            TextInput::make('monthly_price')->label('Місячна ціна (грн)')->numeric()->integer()->minValue(0)->required()->live(),
            TextInput::make('max_bags')->label('Ліміт пакетів')->numeric()->integer()->minValue(1)->required(),
            TextInput::make('max_weight_kg')->label('Ліміт ваги (кг)')->numeric()->integer()->minValue(1)->required(),
            Textarea::make('description')->label('Опис')->rows(3),
            TextInput::make('ui_badge')->label('UI badge')->maxLength(64),
            TextInput::make('ui_subtitle')->label('UI subtitle')->maxLength(255),
            Toggle::make('is_active')->label('Активний')->default(true)->required(),
            TextInput::make('sort_order')->label('Порядок')->numeric()->integer()->minValue(0)->default(0),
            Placeholder::make('reference_preview')
                ->label('Preview рітейл-референсу')
                ->content(function (callable $get): string {
                    $plan = new SubscriptionPlan([
                        'pickups_per_month' => (int) ($get('pickups_per_month') ?? 0),
                        'monthly_price' => (int) ($get('monthly_price') ?? 0),
                    ]);

                    return sprintf(
                        'Референс у роздріб: %d грн, економія: %d грн (%d%%)',
                        $plan->referenceMonthlyTotal(),
                        $plan->economyAmount(),
                        $plan->economyPercent(),
                    );
                }),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Назва')->searchable()->sortable(),
                TextColumn::make('monthly_price')->label('Ціна / міс')->suffix(' грн')->sortable(),
                TextColumn::make('pickups_per_month')->label('Виносів/міс')->sortable(),
                TextColumn::make('max_bags')->label('Пакетів')->sortable(),
                TextColumn::make('max_weight_kg')->label('Кг')->sortable(),
                TextColumn::make('economy_percent')
                    ->label('Економія')
                    ->state(fn (SubscriptionPlan $record): string => $record->economyPercent().' %'),
                IconColumn::make('is_active')->label('Активний')->boolean(),
                TextColumn::make('sort_order')->label('Порядок')->sortable(),
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
            'index' => Pages\ListSubscriptionPlans::route('/'),
            'create' => Pages\CreateSubscriptionPlan::route('/create'),
            'edit' => Pages\EditSubscriptionPlan::route('/{record}/edit'),
        ];
    }
}
