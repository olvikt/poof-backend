<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ❗ НИЧЕГО, что зависит от HTTP / auth / request
    }

    public function boot(): void
    {
        // ❗ Только безопасные вещи
    }
}
