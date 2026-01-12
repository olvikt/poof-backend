<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /* =========================================================
     |  ROLES
     | ========================================================= */

    public const ROLE_ADMIN   = 'admin';
    public const ROLE_COURIER = 'courier';
    public const ROLE_CLIENT  = 'client';

    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_COURIER,
        self::ROLE_CLIENT,
    ];

    /* =========================================================
     |  MASS ASSIGNMENT
     | ========================================================= */

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'is_active',
        'locale',
        'timezone',
    ];

    /* =========================================================
     |  STATUS / ROLE HELPERS
     | ========================================================= */

    public function isActive(): bool
    {
        // Если поле is_active в базе гарантировано boolean и not null — достаточно:
        return (bool) $this->is_active;

        // Если вдруг is_active может быть null и ты хочешь трактовать null как active,
        // замени строку выше на:
        // return (bool) ($this->is_active ?? true);
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function isAdmin(): bool
    {
        return $this->isActive() && $this->hasRole(self::ROLE_ADMIN);
    }

    public function isCourier(): bool
    {
        return $this->isActive() && $this->hasRole(self::ROLE_COURIER);
    }

    public function isClient(): bool
    {
        return $this->isActive() && $this->hasRole(self::ROLE_CLIENT);
    }

    /* =========================================================
     |  RELATIONS
     | ========================================================= */

    public function courierProfile(): HasOne
    {
        return $this->hasOne(Courier::class, 'user_id');
    }

    public function clientProfile(): HasOne
    {
        return $this->hasOne(ClientProfile::class, 'user_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(ClientAddress::class, 'user_id');
    }

    public function defaultAddress(): HasOne
    {
        return $this->hasOne(ClientAddress::class, 'user_id')
            ->where('is_default', true);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'client_id');
    }

    public function takenOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'courier_id');
    }

    /* =========================================================
     |  SERIALIZATION
     | ========================================================= */

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }
}

