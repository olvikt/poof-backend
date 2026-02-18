<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

// =========================================================
// 🔔 Framework Events
// =========================================================
use Illuminate\Auth\Events\Login;

// =========================================================
// 🔔 Domain Events
// =========================================================
use App\Events\OrderCreated;

// =========================================================
// 🎯 Listeners
// =========================================================
use App\Listeners\DispatchOfferForOrder;
use App\Listeners\ResetCourierSessionOnLogin;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [

        /**
         * =========================================================
         *  AUTH → COURIER SESSION RESET
         * =========================================================
         *
         * При каждом логине курьера:
         * – сбрасываем online / busy
         * – приводим session_state в OFFLINE
         *
         * 🔒 Решает баг "курьер остался онлайн после повторного входа"
         * 🔒 Поведение как в Bolt / Glovo
         */
        Login::class => [
            ResetCourierSessionOnLogin::class,
        ],

        /**
         * =========================================================
         *  ORDERS → OFFERS (CORE DISPATCH FLOW)
         * =========================================================
         *
         * Когда заказ создан и перешёл в SEARCHING —
         * запускаем OfferDispatcher (Uber-style)
         */
        OrderCreated::class => [
            DispatchOfferForOrder::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
