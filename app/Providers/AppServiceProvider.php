<?php

namespace App\Providers;

use App\Actions\Orders\Completion\GetPendingConfirmationsForClientAction;
use App\Support\Courier\Observability\CourierRuntimeRequestCollector;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use App\Services\Geocoding\Contracts\GeocoderInterface;
use App\Services\Geocoding\Providers\GooglePlacesProvider;
use Filament\Tables\Columns\TextColumn;
use App\Models\Order;
use App\Models\User;
use App\Observers\OrderObserver;
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
        Order::observe(OrderObserver::class);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        TextColumn::configureUsing(function (TextColumn $component): void {
            if (in_array($component->getName(), ['created_at', 'updated_at'], true)) {
                $component->timezone(config('app.timezone'));
            }
        });

        View::composer('partials.header', function ($view): void {
            $user = auth()->user();

            if (! $user || ! $user->isClient()) {
                $view->with([
                    'pendingConfirmationsCount' => 0,
                    'pendingConfirmationItems' => [],
                ]);

                return;
            }

            $payload = app(GetPendingConfirmationsForClientAction::class)->handle($user);

            if (config('app.debug_header_composer', false)) {
                logger()->info('HEADER_COMPOSER_PENDING', [
                    'auth_id' => auth()->id(),
                    'role' => auth()->user()?->role,
                    'count' => $payload['count'] ?? null,
                ]);
            }

            $view->with([
                'pendingConfirmationsCount' => (int) ($payload['count'] ?? 0),
                'pendingConfirmationItems' => $payload['items'] ?? [],
            ]);
        });
    }
}
