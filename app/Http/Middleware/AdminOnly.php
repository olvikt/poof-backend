<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        // 🔑 используем admin guard
        $user = auth('admin')->user();

        // ❗ ЕСЛИ НЕ ЗАЛОГИНЕН — НЕ МЕШАЕМ
        // Filament сам отправит на /admin/login
        if (! $user) {
            return $next($request);
        }

        // ❌ залогинен, но не админ
        if ($user->role !== 'admin') {
            abort(403);
        }

        return $next($request);
    }
}

