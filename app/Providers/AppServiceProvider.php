<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Geocoding\Contracts\GeocoderInterface;
use App\Services\Geocoding\Providers\GooglePlacesProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            GeocoderInterface::class,
            GooglePlacesProvider::class
        );
    }

    public function boot(): void
    {
        // ❌ НИЧЕГО НЕ РЕГИСТРИРУЕМ ВРУЧНУЮ
        // Все консольные задачи — через routes/console.php
    }
}