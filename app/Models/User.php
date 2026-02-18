<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Storage;
use App\Models\OrderOffer;

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
     |  COURIER SESSION STATES (FSM)
     |  ⚠️ пока НЕ используются в логике
     | ========================================================= */

    public const SESSION_OFFLINE     = 'OFFLINE';
    public const SESSION_READY       = 'READY';
    public const SESSION_SEARCHING   = 'SEARCHING';
    public const SESSION_HAS_OFFER   = 'HAS_OFFER';
    public const SESSION_IN_PROGRESS = 'IN_PROGRESS';

    public const SESSION_STATES = [
        self::SESSION_OFFLINE,
        self::SESSION_READY,
        self::SESSION_SEARCHING,
        self::SESSION_HAS_OFFER,
        self::SESSION_IN_PROGRESS,
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
        'avatar',

        // courier runtime
        'is_online',
        'is_busy',
		
		'session_state',
		
        'last_lat',
        'last_lng',
        'last_seen_at',
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
     |  COURIER RUNTIME STATE (TOP-APP LOGIC)
     | ========================================================= */

    /**
     * Курʼєр онлайн?
     */
    public function isCourierOnline(): bool
    {
        return $this->isCourier() && (bool) $this->is_online;
    }

    /**
     * Может ли курʼєр принимать заказы
     */
    public function canAcceptOrders(): bool
    {
        return $this->isCourierOnline() && ! $this->is_busy;
    }

    /**
     * Перевести курʼєра в Online
     */
    public function goOnline(): void
    {
        if (! $this->isCourier()) {
            return;
        }

        $this->update([
            'is_online'    => true,
            'last_seen_at' => now(),
        ]);
    }

    /**
     * Перевести курʼєра в Offline
     * ⚠️ Offline всегда = not busy
     */
    public function goOffline(): void
    {
        if (! $this->isCourier()) {
            return;
        }

        $this->update([
            'is_online' => false,
            'is_busy'   => false,
        ]);
    }

    /**
     * Пометить курʼєра занятым (есть активный заказ)
     */
    public function markBusy(): void
    {
        if (! $this->isCourier()) {
            return;
        }

        $this->update([
            'is_busy' => true,
        ]);
    }

    /**
     * Освободить курʼєра
     */
    public function markFree(): void
    {
        if (! $this->isCourier()) {
            return;
        }

        $this->update([
            'is_busy' => false,
        ]);
    }

    /**
     * Обновление геолокации курʼєра
     */
    public function updateLocation(float $lat, float $lng): void
    {
        if (! $this->isCourierOnline()) {
            return;
        }

        $this->update([
            'last_lat'     => $lat,
            'last_lng'     => $lng,
            'last_seen_at' => now(),
        ]);
    }
	
	
	/* =========================================================
     |  COURIER SESSION HELPERS (SAFE)
     | ========================================================= */

    /**
     * Установить состояние сессии курʼєра
     * ⚠️ Пока НЕ влияет на бизнес-логику
     */
    public function setSessionState(string $state): void
    {
        if (! $this->isCourier()) {
            return;
        }

        // защита от мусора
        if (! in_array($state, self::SESSION_STATES, true)) {
            return;
        }

        // если состояние не изменилось — ничего не делаем
        if ($this->session_state === $state) {
            return;
        }

        $this->update([
            'session_state' => $state,
        ]);
    }

    /**
     * Получить состояние сессии (с fallback)
     */
    public function getSessionState(): string
    {
        return $this->session_state
            ?? self::SESSION_OFFLINE;
    }
	

    /* =========================================================
     |  RELATIONS
     | ========================================================= */

    /**
     * Профиль курьера (если будет расширение)
     */
    public function courierProfile(): HasOne
    {
        return $this->hasOne(Courier::class, 'user_id');
    }

    /**
     * Все адреса пользователя
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(ClientAddress::class, 'user_id');
    }

    /**
     * Основной адрес пользователя
     */
    public function defaultAddress(): HasOne
    {
        return $this->hasOne(ClientAddress::class, 'user_id')
            ->where('is_default', true)
            ->where('is_active', true);
    }

    /**
     * Заказы клиента
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'client_id');
    }

    /**
     * Заказы, взятые курʼєром
     */
    public function takenOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'courier_id');
    }
	
	 /**
     * Все офферы, показанные курʼєру
     */
    public function orderOffers(): HasMany
    {
        return $this->hasMany(OrderOffer::class, 'courier_id');
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

            // courier runtime
            'is_online'    => 'boolean',
            'is_busy'      => 'boolean',
			
			'last_lat'     => 'float',
            'last_lng'     => 'float',
			
            'last_seen_at' => 'datetime',
			'last_completed_at' => 'datetime',
            'last_offer_at'     => 'datetime',
        ];
    }
}


