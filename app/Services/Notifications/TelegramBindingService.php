<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\TelegramBindToken;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramBindingService
{
    public function generateForCourier(User $courier): array
    {
        $token = Str::random(48);
        $expiresAt = now()->addMinutes((int) config('services.telegram.bind_token_ttl_minutes', 15));

        TelegramBindToken::query()->create([
            'user_id' => $courier->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
        ]);

        $bot = ltrim((string) config('services.telegram.bot_username'), '@');
        $deepLink = sprintf('https://t.me/%s?start=%s', $bot, $token);

        return ['token' => $token, 'deep_link' => $deepLink, 'expires_at' => $expiresAt->toIso8601String()];
    }

    public function bindByToken(string $token, string $chatId, ?string $userId = null, ?string $username = null): User
    {
        $hash = hash('sha256', trim($token));

        return DB::transaction(function () use ($hash, $chatId, $userId, $username): User {
            $row = TelegramBindToken::query()->where('token_hash', $hash)->lockForUpdate()->first();
            abort_if(! $row, 422, 'Invalid token');
            abort_if($row->used_at !== null, 422, 'Token already used');
            abort_if(Carbon::parse($row->expires_at)->isPast(), 422, 'Token expired');

            $courier = User::query()->findOrFail($row->user_id);
            $courier->forceFill([
                'telegram_chat_id' => $chatId,
                'telegram_user_id' => $userId,
                'telegram_username' => $username,
                'telegram_linked_at' => now(),
            ])->save();

            $row->forceFill(['used_at' => now()])->save();

            Log::info('courier_telegram_bound', ['courier_id' => $courier->id, 'telegram_chat_id' => $chatId]);

            return $courier;
        });
    }

    public function unlink(User $courier): void
    {
        $courier->forceFill([
            'telegram_chat_id' => null,
            'telegram_user_id' => null,
            'telegram_username' => null,
            'telegram_linked_at' => null,
        ])->save();

        Log::info('courier_telegram_unlinked', ['courier_id' => $courier->id]);
    }
}
