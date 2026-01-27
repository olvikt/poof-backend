<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Storage;

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
        'avatar', // ✅ ВАЖНО: совпадает с колонкой в БД
    ];

    /* =========================================================
     |  STATUS / ROLE HELPERS
     | ========================================================= */

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, self::ROLES, true)
            && $this->role === $role;
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

    /**
     * Профиль курьера (если пользователь — courier)
     */
    public function courierProfile(): HasOne
    {
        return $this->hasOne(Courier::class, 'user_id');
    }

    /**
     * Основной адрес пользователя (MVP / Bolt-style)
     */
    public function address(): HasOne
    {
        return $this->hasOne(ClientAddress::class, 'user_id')
            ->where('is_default', true);
    }
	
	
	/**
     * Все адреса пользователя
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(ClientAddress::class, 'user_id');
    }

    /**
     * Заказы клиента
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'client_id');
    }

    /**
     * Заказы, взятые курьером
     */
    public function takenOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'courier_id');
    }

    /* =========================================================
     |  ACCESSORS
     | ========================================================= */

    /**
     * URL аватара пользователя (с fallback)
     */
    public function getAvatarUrlAttribute(): string
    {
        if (
            $this->avatar &&
            Storage::disk('public')->exists($this->avatar)
        ) {
            return Storage::url($this->avatar);
        }

        return asset('img/avatars/default.png');
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


