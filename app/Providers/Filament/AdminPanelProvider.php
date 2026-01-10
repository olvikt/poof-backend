<?php

namespace App\Providers\Filament;

use App\Http\Middleware\AdminOnly;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')

            // 🔐 Логин
            ->login()

            // 🎨 Цвета панели
            ->colors([
                'primary' => Color::Amber,
            ])

            /**
             * 🌍 ВНЕШНИЕ ASSETS (ТОЛЬКО Leaflet)
             * НИКАКОГО VITE ЗДЕСЬ
             */
			->assets([
				Css::make(
					'leaflet-css',
					'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'
				),
				Js::make(
					'leaflet-js',
					'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'
				),
				Js::make(
					'orders-map',
					asset('js/filament/orders-map.js')
				),
			])

            /**
             * 📦 Filament: Pages / Resources / Widgets
             */
            ->discoverResources(
                in: app_path('Filament/Resources'),
                for: 'App\\Filament\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/Pages'),
                for: 'App\\Filament\\Pages'
            )
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(
                in: app_path('Filament/Widgets'),
                for: 'App\\Filament\\Widgets'
            )
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])

            /**
             * 🧱 Middleware панели
             */
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])

            /**
             * 🔐 Доступ только для админов
             */
            ->authMiddleware([
                Authenticate::class,
                AdminOnly::class,
            ]);
    }
}
