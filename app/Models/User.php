<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Mass assignable attributes
     */
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
        return (bool) $this->is_active;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin' && $this->isActive();
    }

    public function isCourier(): bool
    {
        return $this->role === 'courier' && $this->isActive();
    }

    public function isClient(): bool
    {
        return $this->role === 'client' && $this->isActive();
    }

    /* =========================================================
     |  RELATIONS
     | ========================================================= */

    /**
     * Профиль курьера (если пользователь — courier)
     */
    public function courierProfile()
    {
        return $this->hasOne(Courier::class, 'user_id');
    }

    /**
     * Профиль клиента (настройки, бонусы)
     */
    public function clientProfile()
    {
        return $this->hasOne(ClientProfile::class, 'user_id');
    }

    /**
     * Адреса клиента (как в Uber / Glovo)
     */
    public function addresses()
    {
        return $this->hasMany(ClientAddress::class, 'user_id');
    }

    /**
     * Адрес по умолчанию
     */
    public function defaultAddress()
    {
        return $this->hasOne(ClientAddress::class, 'user_id')
            ->where('is_default', true);
    }

    /**
     * Заказы, созданные клиентом
     */
    public function orders()
    {
        return $this->hasMany(Order::class, 'client_id');
    }

    /**
     * Заказы, которые курьер взял в работу
     */
    public function takenOrders()
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

