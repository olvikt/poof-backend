<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use App\Models\User;

class ResetCourierSessionOnLogin
{
    /**
     * Срабатывает после успешного логина
     */
    public function handle(Login $event): void
    {
        /** @var User $user */
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        // Обновляем время последнего входа для мониторинга в админке.
        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        // 🔒 Дополнительная логика только для курьеров
        if (! $user->isCourier()) {
            return;
        }

        // 🧹 Жёсткий сброс сессии курьера через единый state API
        $user->goOffline(force: true);
    }
}
