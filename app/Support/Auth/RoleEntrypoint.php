<?php

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Http\Request;

class RoleEntrypoint
{
    public const ENTRY_CLIENT = 'client';
    public const ENTRY_COURIER = 'courier';

    public static function detect(Request $request): string
    {
        $host = mb_strtolower((string) $request->getHost());

        if ($host === 'courier.poof.com.ua' || str_starts_with($host, 'courier.')) {
            return self::ENTRY_COURIER;
        }

        return self::ENTRY_CLIENT;
    }

    public static function expectedRegistrationRole(Request $request): string
    {
        if ($request->is('courier/register') || self::detect($request) === self::ENTRY_COURIER) {
            return User::ROLE_COURIER;
        }

        return User::ROLE_CLIENT;
    }

    public static function homeByRole(string $role): string
    {
        return $role === User::ROLE_COURIER
            ? route('courier.home')
            : route('client.home');
    }

    public static function loginRouteForEntrypoint(string $entrypoint): string
    {
        return $entrypoint === self::ENTRY_COURIER
            ? route('login.courier')
            : route('login');
    }

    public static function normalizeNextWithinRoleSpace(?string $next, string $role): ?string
    {
        if (! is_string($next) || trim($next) === '') {
            return null;
        }

        if (! str_starts_with($next, '/')) {
            return null;
        }

        if ($role === User::ROLE_COURIER) {
            return str_starts_with($next, '/courier') ? $next : null;
        }

        if (str_starts_with($next, '/courier')) {
            return null;
        }

        return $next;
    }
}
