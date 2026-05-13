<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CourierResource\Pages;
use App\Models\Courier;
use App\Models\TelegramAdminNotification;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;

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
                ->relationship(name: 'user', titleAttribute: 'email', modifyQueryUsing: fn ($query) => $query->where('role', 'courier')->whereDoesntHave('courier'))
                ->searchable()->required()->disabled(fn (?Courier $record) => $record !== null)->rules(fn (?Courier $record): array => $record ? [] : ['unique:couriers,user_id']),
            Section::make('Courier profile')->relationship('user')->schema([
                TextInput::make('name')->label('Full name')->maxLength(255)->required(),
                TextInput::make('email')->email()->maxLength(255)->required(),
                TextInput::make('phone')->tel()->maxLength(255),
                TextInput::make('residence_address')->label('Address')->maxLength(500),
            ])->columns(2)->visible(fn (?Courier $record): bool => $record !== null),
            Select::make('status')->options([
                Courier::STATUS_OFFLINE => 'Offline',
                Courier::STATUS_ONLINE => 'Online',
                Courier::STATUS_ASSIGNED => 'Assigned',
                Courier::STATUS_DELIVERING => 'Delivering',
            ])->default(Courier::STATUS_OFFLINE)->disabled(fn (?Courier $record) => $record !== null)->dehydrated(fn (?Courier $record) => $record === null)->required(),
            TextInput::make('city')->maxLength(255),
            Select::make('transport')->options(['walk' => 'Walk', 'bike' => 'Bike', 'car' => 'Car'])->default('walk')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user.name')->label("Ім'я")->searchable()->sortable(),
            TextColumn::make('user.email')->label('Email')->searchable()->sortable(),
            TextColumn::make('status')->badge()->colors(['success' => 'online', 'warning' => 'busy', 'gray' => 'offline']),
            TextColumn::make('city')->sortable(),
            TextColumn::make('transport')->badge(),
            IconColumn::make('is_verified')->boolean(),
            IconColumn::make('user.telegram_chat_id')->label('Telegram прив’язано')->boolean(fn (Courier $record): bool => ! empty($record->user?->telegram_chat_id)),
            TextColumn::make('user.telegram_username')->label('Telegram username')->placeholder('—')->toggleable(),
            TextColumn::make('user.telegram_linked_at')->label('Підʼєднано о')->dateTime('Y-m-d H:i')->placeholder('—')->toggleable(),
            IconColumn::make('user.telegram_notifications_orders_enabled')->label('Замовлення')->boolean()->toggleable(),
            IconColumn::make('user.telegram_notifications_marketing_enabled')->label('Новини та акції')->boolean()->toggleable(),
        ])->filters([
            Tables\Filters\Filter::make('telegram_linked')->label('Telegram прив’язано')->query(fn ($query) => $query->whereHas('user', fn ($q) => $q->whereNotNull('telegram_chat_id'))),
            Tables\Filters\Filter::make('telegram_unlinked')->label('Telegram не прив’язано')->query(fn ($query) => $query->whereHas('user', fn ($q) => $q->whereNull('telegram_chat_id'))),
            Tables\Filters\Filter::make('orders_enabled')->label('Увімкнено сповіщення про замовлення')->query(fn ($query) => $query->whereHas('user', fn ($q) => $q->where('telegram_notifications_orders_enabled', true))),
            Tables\Filters\Filter::make('marketing_enabled')->label('Увімкнено новини та акції')->query(fn ($query) => $query->whereHas('user', fn ($q) => $q->where('telegram_notifications_marketing_enabled', true))),
        ])->defaultSort('id', 'desc')->actions([
            Tables\Actions\Action::make('send_telegram_notification')->label('Надіслати Telegram сповіщення')->icon('heroicon-o-paper-airplane')->form([
                TextInput::make('title')->label('Заголовок')->maxLength(120),
                Textarea::make('message')->label('Текст')->required()->maxLength(1000)->rows(4),
                Select::make('notification_type')->label('Тип')->options(['order_service' => 'Замовлення/сервіс', 'news_marketing' => 'Новини/маркетинг'])->required(),
                Toggle::make('is_emergency')->label('Сервісне/екстрене (ігнорувати преференцію замовлень)')->default(false),
                Placeholder::make('preview')->label('Превʼю')->content(fn (callable $get): string => trim(((string) $get('title')) . "\n\n" . ((string) $get('message')))),
            ])->action(fn (Courier $record, array $data) => static::dispatchAdminTelegramNotification(auth()->id(), collect([$record]), $data)),
            Tables\Actions\Action::make('verification_requests')->label('Verification requests')->icon('heroicon-o-identification')->url(fn (Courier $record): string => CourierVerificationRequestResource::getUrl('index', ['tableFilters' => ['courier_id' => ['value' => $record->user_id]]])),
            Tables\Actions\EditAction::make(),
        ])->bulkActions([
            Tables\Actions\BulkAction::make('send_telegram_notification_bulk')->label('Надіслати Telegram сповіщення')->icon('heroicon-o-paper-airplane')->requiresConfirmation()->form([
                TextInput::make('title')->label('Заголовок')->maxLength(120),
                Textarea::make('message')->label('Текст')->required()->maxLength(1000)->rows(4),
                Select::make('notification_type')->label('Тип')->options(['order_service' => 'Замовлення/сервіс', 'news_marketing' => 'Новини/маркетинг'])->required(),
                Toggle::make('is_emergency')->label('Сервісне/екстрене (ігнорувати преференцію замовлень)')->default(false),
            ])->action(fn (Collection $records, array $data) => static::dispatchAdminTelegramNotification(auth()->id(), $records, $data)),
            Tables\Actions\DeleteBulkAction::make(),
        ]);
    }

    private static function dispatchAdminTelegramNotification(?int $adminId, iterable $records, array $data): void
    {
        $sent = $skippedNotLinked = $skippedPreference = $failed = 0;
        $title = trim((string) ($data['title'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));
        $type = (string) ($data['notification_type'] ?? 'order_service');
        $isEmergency = (bool) ($data['is_emergency'] ?? false);
        $text = trim($title !== '' ? $title . "\n\n" . $message : $message);

        foreach ($records as $courier) {
            $user = $courier->user;
            $status = TelegramAdminNotification::STATUS_SENT;
            $error = null;

            if (! $user || empty($user->telegram_chat_id)) { $status = TelegramAdminNotification::STATUS_SKIPPED; $error = 'not_linked'; $skippedNotLinked++; }
            elseif ($type === 'news_marketing' && ! (bool) $user->telegram_notifications_marketing_enabled) { $status = TelegramAdminNotification::STATUS_SKIPPED; $error = 'marketing_disabled'; $skippedPreference++; }
            elseif ($type === 'order_service' && ! $isEmergency && ! (bool) $user->telegram_notifications_orders_enabled) { $status = TelegramAdminNotification::STATUS_SKIPPED; $error = 'orders_disabled'; $skippedPreference++; }
            else {
                $response = Http::timeout(5)->post(sprintf('https://api.telegram.org/bot%s/sendMessage', (string) config('services.telegram.bot_token')), ['chat_id' => $user->telegram_chat_id, 'text' => $text]);
                if ($response->failed()) { $status = TelegramAdminNotification::STATUS_FAILED; $error = $response->body(); $failed++; } else { $sent++; }
            }

            TelegramAdminNotification::query()->create([
                'admin_id' => $adminId,
                'courier_id' => $user?->id,
                'notification_type' => $type,
                'status' => $status,
                'title' => $title ?: null,
                'message' => $message,
                'is_emergency' => $isEmergency,
                'telegram_error' => $error,
            ]);
        }

        Notification::make()->title('Результат відправки')->body("Надіслано: {$sent}; Пропущено (не прив’язано): {$skippedNotLinked}; Пропущено (преференції): {$skippedPreference}; Помилки: {$failed}")->success()->send();
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCouriers::route('/'),
            'create' => Pages\CreateCourier::route('/create'),
            'edit' => Pages\EditCourier::route('/{record}/edit'),
        ];
    }
}
