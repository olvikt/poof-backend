<?php

namespace App\Http\Middleware;

use App\Support\Auth\RoleEntrypoint;
use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    protected function redirectTo($request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        if ($request->is('courier/*')) {
            return route('login.courier', ['next' => '/'.$request->path()]);
        }

        if ($request->is('client/*')) {
            return route('login', ['next' => '/'.$request->path()]);
        }

        return RoleEntrypoint::loginRouteForEntrypoint(RoleEntrypoint::detect($request));
    }
}
