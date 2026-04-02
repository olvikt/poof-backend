<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Filament\Resources\ClientResource\RelationManagers\AddressesRelationManager;
use App\Filament\Resources\ClientResource\RelationManagers\OrdersRelationManager;
use App\Models\Order;
use App\Models\User;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClientResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Клиенты';

    protected static ?string $pluralLabel = 'Клиенты';

    protected static ?string $modelLabel = 'Клиент';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Имя')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Дата регистрации')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('orders_count')
                    ->label('Количество заказов')
                    ->sortable(),
                TextColumn::make('addresses_count')
                    ->label('Количество адресов')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Основная информация')
                    ->schema([
                        TextEntry::make('id')->label('ID'),
                        TextEntry::make('name')->label('Имя'),
                        TextEntry::make('phone')->label('Телефон')->default('—'),
                        TextEntry::make('email')->label('Email')->default('—'),
                        TextEntry::make('created_at')
                            ->label('Дата регистрации')
                            ->dateTime('d.m.Y H:i'),
                    ])
                    ->columns(2),

                Section::make('Статистика оплат')
                    ->schema([
                        TextEntry::make('stats.total_orders')
                            ->label('Всего заказов')
                            ->state(fn (User $record): int => $record->orders()->count()),
                        TextEntry::make('stats.paid_orders')
                            ->label('Всего оплаченных заказов')
                            ->state(fn (User $record): int => $record->orders()->where('payment_status', Order::PAY_PAID)->count()),
                        TextEntry::make('stats.unpaid_orders')
                            ->label('Всего неоплаченных заказов')
                            ->state(fn (User $record): int => $record->orders()->where('payment_status', '!=', Order::PAY_PAID)->count()),
                        TextEntry::make('stats.paid_sum')
                            ->label('Общая сумма оплаченных заказов')
                            ->money('UAH')
                            ->state(fn (User $record): int => (int) $record->orders()->where('payment_status', Order::PAY_PAID)->sum('price')),
                        TextEntry::make('stats.average_check')
                            ->label('Средний чек')
                            ->money('UAH')
                            ->state(function (User $record): int {
                                $avg = $record->orders()->where('payment_status', Order::PAY_PAID)->avg('price');

                                return (int) round((float) ($avg ?? 0));
                            }),
                        TextEntry::make('stats.last_paid_at')
                            ->label('Последний оплаченный заказ')
                            ->state(function (User $record): string {
                                $lastPaid = $record->orders()
                                    ->where('payment_status', Order::PAY_PAID)
                                    ->latest('updated_at')
                                    ->value('updated_at');

                                if ($lastPaid === null) {
                                    return '—';
                                }

                                return \Illuminate\Support\Carbon::parse($lastPaid)->format('d.m.Y H:i');
                            }),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            OrdersRelationManager::class,
            AddressesRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('role', User::ROLE_CLIENT)
            ->withCount(['orders', 'addresses']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'view' => Pages\ViewClient::route('/{record}'),
        ];
    }
}
