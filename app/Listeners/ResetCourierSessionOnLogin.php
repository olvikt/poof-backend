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

        // Сначала выравниваем runtime-state на основе активных заказов (self-heal).
        $user->repairCourierRuntimeState();
        $user->refresh();

        $activeOrderStatus = $user->takenOrders()
            ->activeForCourier()
            ->value('status');

        // Для свободного курьера сохраняем прежнее поведение: логин сбрасывает в OFFLINE.
        if ($activeOrderStatus === null) {
            $user->goOffline(force: true);

            return;
        }

        // Активный заказ запрещает offline/free на логине.
        $user->repairCourierRuntimeState();
        $user->refresh();
    }
}
