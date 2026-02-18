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

        // 🔒 Только для курьеров
        if (! $user instanceof User || ! $user->isCourier()) {
            return;
        }

        // 🧹 Жёсткий сброс сессии курьера
        $user->forceFill([
            'is_online'    => false,
            'is_busy'      => false,
            'session_state'=> User::SESSION_OFFLINE,
        ])->save();
    }
}
