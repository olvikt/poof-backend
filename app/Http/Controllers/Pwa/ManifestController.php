<?php

namespace App\Http\Controllers\Pwa;

use Illuminate\Http\JsonResponse;

class ManifestController
{
    public function client(): JsonResponse
    {
        return response()->json([
            'id' => '/',
            'name' => 'POOF — клієнтський застосунок',
            'short_name' => 'POOF Client',
            'description' => 'Сервіс швидкого виносу сміття для клієнтів',
            'start_url' => '/client',
            'scope' => '/',
            'display' => 'standalone',
            'display_override' => ['standalone', 'minimal-ui'],
            'orientation' => 'portrait',
            'background_color' => '#18191f',
            'theme_color' => '#18191f',
            'icons' => [
                ['src' => '/assets/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/assets/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => '/assets/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => 'Створити замовлення', 'short_name' => 'Замовлення', 'url' => '/client/order/create'],
                ['name' => 'Мої замовлення', 'short_name' => 'Замовлення', 'url' => '/client/orders'],
                ['name' => 'Профіль', 'short_name' => 'Профіль', 'url' => '/client/profile'],
            ],
            // Asset preparation TODO: dedicated client screenshots/icons can be plugged in separate asset-only PR.
            'screenshots' => [
                ['src' => '/assets/screenshots/home.png', 'sizes' => '1080x1920', 'type' => 'image/png', 'form_factor' => 'narrow'],
            ],
        ]);
    }

    public function courier(): JsonResponse
    {
        return response()->json([
            'id' => '/courier',
            'name' => 'POOF — курʼєрський застосунок',
            'short_name' => 'POOF Courier',
            'description' => 'Курʼєрський кабінет POOF',
            'start_url' => '/courier',
            'scope' => '/courier',
            'display' => 'standalone',
            'display_override' => ['standalone', 'minimal-ui'],
            'orientation' => 'portrait',
            'background_color' => '#18191f',
            'theme_color' => '#18191f',
            // Asset preparation TODO: courier-specific icons should be added in a separate PR without code refactor noise.
            'icons' => [
                ['src' => '/assets/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/assets/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
            ],
            'shortcuts' => [
                ['name' => 'Доступні замовлення', 'short_name' => 'Доступні', 'url' => '/courier/orders'],
                ['name' => 'Мої замовлення', 'short_name' => 'Мої', 'url' => '/courier/my-orders'],
            ],
        ]);
    }
}
