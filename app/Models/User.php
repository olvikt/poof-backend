<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Storage;
use App\Models\OrderOffer;

class User extends Authenticatable implements FilamentUser
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
    public const SESSION_ASSIGNED    = 'ASSIGNED';

    public const SESSION_STATES = [
        self::SESSION_OFFLINE,
        self::SESSION_READY,
        self::SESSION_SEARCHING,
        self::SESSION_HAS_OFFER,
        self::SESSION_IN_PROGRESS,
        self::SESSION_ASSIGNED,
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
        'is_verified',
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
        'last_login_at',
    ];

    /* =========================================================
     |  STATUS / ROLE HELPERS
     | ========================================================= */

    public function isActive(): bool
    {
        // Старые записи могли быть созданы до добавления поля is_active.
        // Для обратной совместимости считаем null как активного пользователя.
        return $this->is_active !== false;
    }

    public function hasRole(string $role): bool
    {
        $expectedRole = strtolower(trim($role));
        $currentRole = strtolower(trim((string) $this->role));

        return in_array($expectedRole, self::ROLES, true)
            && $currentRole === $expectedRole;
    }

    public function isAdmin(): bool
    {
        return $this->isActive() && $this->hasRole(self::ROLE_ADMIN);
    }

    /* =========================================================
     |  FILAMENT ADMIN ACCESS
     | ========================================================= */

    public function canAccessPanel(Panel $panel): bool
    {
        // Filament v3 gate for /admin panel access.
        return $this->isAdmin();
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
     * Каноническое runtime-состояние курьера.
     */
    public function courierRuntimeState(): ?string
    {
        if (! $this->isCourier()) {
            return null;
        }

        $this->repairCourierRuntimeState();

        return $this->courierProfile?->status;
    }

    /**
     * Курʼєр онлайн?
     */
    public function isCourierOnline(): bool
    {
        return in_array($this->courierRuntimeState(), [
            Courier::STATUS_ONLINE,
            Courier::STATUS_ASSIGNED,
            Courier::STATUS_DELIVERING,
        ], true);
    }

    /**
     * Единый источник правды занятости курьера в доменной логике accept.
     */
    public function isBusyForAccept(): bool
    {
        if (in_array($this->courierRuntimeState(), [
            Courier::STATUS_ASSIGNED,
            Courier::STATUS_DELIVERING,
        ], true)) {
            return true;
        }

        return $this->takenOrders()
            ->activeForCourier()
            ->exists();
    }

    /**
     * Может ли курʼєр принимать заказы
     */
    public function canAcceptOrders(): bool
    {
        $this->repairCourierRuntimeState();

        return $this->isCourierOnline() && ! $this->isBusyForAccept();
    }

    public function hasActiveCourierOrder(): bool
    {
        if (! $this->isCourier()) {
            return false;
        }

        return $this->takenOrders()->activeForCourier()->exists();
    }

    public function repairCourierRuntimeState(): void
    {
        if (! $this->isCourier()) {
            return;
        }

        $courier = $this->courierProfile;

        if (! $courier) {
            return;
        }

        $activeOrderStatus = $this->takenOrders()
            ->activeForCourier()
            ->orderByRaw("CASE WHEN status = ? THEN 0 ELSE 1 END", [Order::STATUS_IN_PROGRESS])
            ->value('status');

        if ($activeOrderStatus !== null) {
            $targetStatus = $activeOrderStatus === Order::STATUS_IN_PROGRESS
                ? Courier::STATUS_DELIVERING
                : Courier::STATUS_ASSIGNED;

            if ((string) $courier->status !== $targetStatus) {
                $courier->update(['status' => $targetStatus]);
            }

            $this->syncRuntimeFlagsFromCourierState($targetStatus);

            return;
        }

        $targetStatus = (string) $courier->status;

        if (in_array($targetStatus, [Courier::STATUS_ASSIGNED, Courier::STATUS_DELIVERING], true)) {
            $targetStatus = Courier::STATUS_ONLINE;
            $courier->update(['status' => $targetStatus]);
        }

        $this->syncRuntimeFlagsFromCourierState($targetStatus);
    }

    public function goOnline(): void
    {
        $this->transitionCourierState(Courier::STATUS_ONLINE);
    }

    /**
     * Перевести курʼєра в Offline
     * ⚠️ Offline всегда = not busy
     */
    public function goOffline(bool $force = false): void
    {
        $this->transitionCourierState(Courier::STATUS_OFFLINE, $force);
    }

    /**
     * Пометить курʼєра занятым (есть активный заказ)
     */
    public function markBusy(): void
    {
        $this->transitionCourierState(Courier::STATUS_ASSIGNED);
    }

    /**
     * Освободить курʼєра
     */
    public function markFree(): void
    {
        $this->transitionCourierState(Courier::STATUS_ONLINE);
    }

    public function markDelivering(): void
    {
        $this->transitionCourierState(Courier::STATUS_DELIVERING);
    }

    /**
     * Обновление геолокации курʼєра
     */
    public function updateLocation(float $lat, float $lng): void
    {
        $this->repairCourierRuntimeState();

        if (! in_array($this->courierRuntimeState(), Courier::ACTIVE_MAP_STATUSES, true)) {
            return;
        }

        $this->update([
            'last_lat'     => $lat,
            'last_lng'     => $lng,
            'last_seen_at' => now(),
        ]);

        $this->courierProfile()->update([
            'last_location_at' => now(),
        ]);
    }

    public function transitionCourierState(string $toStatus, bool $force = false): void
    {
        if (! $this->isCourier()) {
            return;
        }

        $courier = $this->courierProfile;

        if (! $courier) {
            return;
        }

        $fromStatus = (string) $courier->status;

        $activeOrderStatus = $this->takenOrders()
            ->activeForCourier()
            ->orderByRaw("CASE WHEN status = ? THEN 0 ELSE 1 END", [Order::STATUS_IN_PROGRESS])
            ->value('status');

        if ($activeOrderStatus !== null) {
            $enforcedStatus = $activeOrderStatus === Order::STATUS_IN_PROGRESS
                ? Courier::STATUS_DELIVERING
                : Courier::STATUS_ASSIGNED;

            if ($fromStatus !== $enforcedStatus) {
                $courier->update(['status' => $enforcedStatus]);
                $fromStatus = $enforcedStatus;
            }

            $this->syncRuntimeFlagsFromCourierState($enforcedStatus);

            if (in_array($toStatus, [Courier::STATUS_OFFLINE, Courier::STATUS_ONLINE], true)) {
                return;
            }

            $toStatus = $enforcedStatus;
        }

        if (! $force && ! $this->canTransitionCourierState($fromStatus, $toStatus)) {
            return;
        }

        if ($fromStatus !== $toStatus) {
            $courier->update(['status' => $toStatus]);
        }

        $this->syncRuntimeFlagsFromCourierState($toStatus);
    }

    private function canTransitionCourierState(string $fromStatus, string $toStatus): bool
    {
        if ($fromStatus === $toStatus) {
            return true;
        }

        $allowed = [
            Courier::STATUS_OFFLINE => [Courier::STATUS_ONLINE],
            Courier::STATUS_ONLINE => [Courier::STATUS_ASSIGNED, Courier::STATUS_OFFLINE],
            Courier::STATUS_ASSIGNED => [Courier::STATUS_DELIVERING],
            Courier::STATUS_DELIVERING => [Courier::STATUS_ONLINE],
        ];

        return in_array($toStatus, $allowed[$fromStatus] ?? [], true);
    }

    private function syncRuntimeFlagsFromCourierState(string $status): void
    {
        $now = now();

        $stateMap = [
            Courier::STATUS_OFFLINE => ['is_online' => false, 'is_busy' => false, 'session_state' => self::SESSION_OFFLINE],
            Courier::STATUS_ONLINE => ['is_online' => true, 'is_busy' => false, 'session_state' => self::SESSION_READY],
            Courier::STATUS_ASSIGNED => ['is_online' => true, 'is_busy' => true, 'session_state' => self::SESSION_ASSIGNED],
            Courier::STATUS_DELIVERING => ['is_online' => true, 'is_busy' => true, 'session_state' => self::SESSION_IN_PROGRESS],
        ];

        $sync = $stateMap[$status] ?? null;

        if (! $sync) {
            return;
        }

        if ($sync['is_online']) {
            $sync['last_seen_at'] = $now;
        }

        $this->update($sync);
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
     * Courier profile relation alias for Filament/resource queries.
     */
    public function courier(): HasOne
    {
        return $this->courierProfile();
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
            'phone_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'is_verified'       => 'boolean',

            // courier runtime
            'is_online'    => 'boolean',
            'is_busy'      => 'boolean',
			
			'last_lat'     => 'float',
            'last_lng'     => 'float',
			
            'last_seen_at' => 'datetime',
            'last_login_at' => 'datetime',
			'last_completed_at' => 'datetime',
            'last_offer_at'     => 'datetime',
        ];
    }
}
