<?php

namespace App\Providers;

use App\Support\Courier\Observability\CourierRuntimeRequestCollector;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use App\Services\Geocoding\Contracts\GeocoderInterface;
use App\Services\Geocoding\Providers\GooglePlacesProvider;
use Filament\Tables\Columns\TextColumn;
use App\Models\User;
use App\Observers\UserObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            GeocoderInterface::class,
            GooglePlacesProvider::class
        );

        $this->app->scoped(
            CourierRuntimeRequestCollector::class,
            fn (): CourierRuntimeRequestCollector => new CourierRuntimeRequestCollector(),
        );
    }

    public function boot(): void
    {
        User::observe(UserObserver::class);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        TextColumn::configureUsing(function (TextColumn $component): void {
            if (in_array($component->getName(), ['created_at', 'updated_at'], true)) {
                $component->timezone(config('app.timezone'));
            }
        });
    }
}
