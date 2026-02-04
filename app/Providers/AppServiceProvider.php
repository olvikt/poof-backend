<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Geocoding\Contracts\GeocoderInterface;
use App\Services\Geocoding\Providers\GooglePlacesProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 🔑 Binding геокодера (backend only, безопасно)
        $this->app->bind(
            GeocoderInterface::class,
            GooglePlacesProvider::class
        );
    }

    public function boot(): void
    {
        // ❗ Только безопасные вещи
    }
}
