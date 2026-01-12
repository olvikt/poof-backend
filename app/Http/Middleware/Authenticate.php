<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    protected function redirectTo($request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        // клиентская часть
        if ($request->is('client/*')) {
            return '/login';
        }

        // админку НЕ ТРОГАЕМ
        return null;
    }
}
