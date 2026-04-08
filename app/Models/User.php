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
use Illuminate\Support\Facades\Log;
use App\Models\OrderOffer;
use App\Notifications\ResetPasswordPoofNotification;
use App\Support\CourierRuntimeSnapshot;
use App\Support\CourierRuntimeRepairTelemetry;

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
        'residence_address',
        'courier_verification_status',

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

    public function courierRuntimeSnapshot(): ?array
    {
        return CourierRuntimeSnapshot::fromUser($this);
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

    /**
     * Reconcile canonical courier runtime state and project compatibility mirrors.
     *
     * @return array<string,mixed>|null
     */
    public function repairCourierRuntimeState(bool $returnCanonicalRuntime = false): ?array
    {
        if (! $this->isCourier()) {
            return null;
        }

        $this->loadMissing('courierProfile');
        $courier = $this->courierProfile;

        if (! $courier) {
            return null;
        }

        $runtime = $this->resolveCanonicalCourierRuntime($courier);

        if (! is_array($runtime)) {
            return null;
        }

        $targetStatus = (string) ($runtime['status'] ?? Courier::STATUS_OFFLINE);
        $activeOrderStatus = $runtime['active_order_status'] ?? null;
        $statusRepairSource = (string) ($runtime['status_repair_source'] ?? 'repair.enforce_status');

        if (($runtime['status_repair_reason'] ?? null) === 'unknown_status') {
            Log::warning('courier_runtime_unknown_status_normalized', [
                'flow' => 'courier_presence',
                'user_id' => $this->id,
                'courier_status' => (string) $courier->status,
            ]);
        }

        if (($runtime['status_repair_reason'] ?? null) === 'paused_normalized_to_offline') {
            if (config('courier_runtime.incident_logging.enabled', false)) {
                Log::warning('forced_repair_or_guard_reason', [
                    'flow' => 'courier_presence',
                    'user_id' => $this->id,
                    'reason' => 'paused_normalized_to_offline',
                    'courier_status_before' => (string) $courier->status,
                ]);
            }

        }

        if (($runtime['status_repair_reason'] ?? null) === 'orphan_busy_status_normalized_to_online') {
            if (config('courier_runtime.incident_logging.enabled', false)) {
                Log::warning('forced_repair_or_guard_reason', [
                    'flow' => 'courier_presence',
                    'user_id' => $this->id,
                    'reason' => 'orphan_busy_status_normalized_to_online',
                    'courier_status_before' => (string) $courier->status,
                ]);
            }

        }

        if ((string) $courier->status !== $targetStatus) {
            $before = ['status' => (string) $courier->status];
            $courier->update(['status' => $targetStatus]);
            CourierRuntimeRepairTelemetry::emitIfChanged(
                userId: (int) $this->id,
                courierId: (int) $courier->id,
                before: $before,
                after: ['status' => $targetStatus],
                hadActiveOrder: $activeOrderStatus !== null,
                courierStatus: $targetStatus,
                sourceContext: $statusRepairSource,
            );
        }

        $this->syncRuntimeFlagsFromCourierState($targetStatus, true, 'repair.sync_flags_projection');

        return $returnCanonicalRuntime ? $runtime : null;
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
            if (config('courier_runtime.incident_logging.enabled', false)) {
                Log::warning('forced_repair_or_guard_reason', [
                    'flow' => 'courier_presence',
                    'user_id' => $this->id,
                    'reason' => 'transition_blocked_by_fsm',
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                    'force' => $force,
                ]);
            }

            return;
        }

        if ($fromStatus !== $toStatus) {
            $statusUpdate = ['status' => $toStatus];

            if ($toStatus === Courier::STATUS_ONLINE) {
                $statusUpdate['last_location_at'] = now();
            }

            $courier->update($statusUpdate);

            if (config('courier_runtime.incident_logging.enabled', false)) {
                Log::info('courier_runtime_status_transition', [
                    'flow' => 'courier_presence',
                    'courier_id' => $this->id,
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                    'force' => $force,
                    'has_active_order' => $activeOrderStatus !== null,
                    'active_order_status' => $activeOrderStatus,
                    'last_location_at' => $courier->last_location_at?->toIso8601String(),
                ]);
            }
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
            Courier::STATUS_ASSIGNED => [Courier::STATUS_DELIVERING, Courier::STATUS_ONLINE],
            Courier::STATUS_DELIVERING => [Courier::STATUS_ONLINE],
            Courier::STATUS_PAUSED => [Courier::STATUS_ONLINE, Courier::STATUS_OFFLINE],
        ];

        return in_array($toStatus, $allowed[$fromStatus] ?? [], true);
    }

    private function syncRuntimeFlagsFromCourierState(string $status, bool $fromRepair = false, string $sourceContext = 'runtime.sync_flags'): void
    {
        $now = now();

        // Compatibility projection layer only:
        // users.is_online / users.is_busy / users.session_state are derived mirrors
        // and MUST NOT be treated as canonical runtime truth.
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

        $before = [
            'is_online' => (bool) $this->is_online,
            'is_busy' => (bool) $this->is_busy,
            'session_state' => $this->session_state,
        ];

        $updatePayload = [];

        foreach (['is_online', 'is_busy', 'session_state'] as $field) {
            $target = $sync[$field];
            $current = $before[$field];

            if ($field !== 'session_state') {
                $target = (bool) $target;
                $current = (bool) $current;
            }

            if ($current !== $target) {
                $updatePayload[$field] = $sync[$field];
            }
        }

        if (! $fromRepair && ($sync['is_online'] ?? false)) {
            $updatePayload['last_seen_at'] = $now;
        }

        if ($updatePayload === []) {
            return;
        }

        $this->update($updatePayload);

        if ($fromRepair) {
            CourierRuntimeRepairTelemetry::emitIfChanged(
                userId: (int) $this->id,
                courierId: $this->courierProfile?->id,
                before: $before,
                after: [
                    'is_online' => (bool) ($updatePayload['is_online'] ?? $before['is_online']),
                    'is_busy' => (bool) ($updatePayload['is_busy'] ?? $before['is_busy']),
                    'session_state' => (string) ($updatePayload['session_state'] ?? $before['session_state']),
                ],
                hadActiveOrder: in_array($status, [Courier::STATUS_ASSIGNED, Courier::STATUS_DELIVERING], true),
                courierStatus: (string) optional($this->courierProfile)->status,
                sourceContext: $sourceContext,
            );
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private function resolveCanonicalCourierRuntime(Courier $courier): ?array
    {
        $activeOrderStatus = $this->takenOrders()
            ->activeForCourier()
            ->orderByRaw("CASE WHEN status = ? THEN 0 ELSE 1 END", [Order::STATUS_IN_PROGRESS])
            ->value('status');

        $currentStatus = (string) $courier->status;
        $targetStatus = $currentStatus;
        $statusRepairReason = null;
        $statusRepairSource = null;

        if ($activeOrderStatus !== null) {
            $targetStatus = $activeOrderStatus === Order::STATUS_IN_PROGRESS
                ? Courier::STATUS_DELIVERING
                : Courier::STATUS_ASSIGNED;

            if ($currentStatus !== $targetStatus) {
                $statusRepairReason = 'active_order_enforced_status';
                $statusRepairSource = 'repair.active_order_enforce_status';
            }
        } else {
            if (! in_array($targetStatus, [
                Courier::STATUS_OFFLINE,
                Courier::STATUS_ONLINE,
                Courier::STATUS_ASSIGNED,
                Courier::STATUS_DELIVERING,
                Courier::STATUS_PAUSED,
            ], true)) {
                $targetStatus = Courier::STATUS_OFFLINE;
                $statusRepairReason = 'unknown_status';
                $statusRepairSource = 'repair.normalize_unknown_status';
            }

            if ($targetStatus === Courier::STATUS_PAUSED) {
                $targetStatus = Courier::STATUS_OFFLINE;
                $statusRepairReason = 'paused_normalized_to_offline';
                $statusRepairSource = 'repair.normalize_paused_status';
            }

            if (in_array($targetStatus, [Courier::STATUS_ASSIGNED, Courier::STATUS_DELIVERING], true)) {
                $targetStatus = Courier::STATUS_ONLINE;
                $statusRepairReason = 'orphan_busy_status_normalized_to_online';
                $statusRepairSource = 'repair.normalize_orphan_busy_status';
            }
        }

        $isOnline = in_array($targetStatus, [
            Courier::STATUS_ONLINE,
            Courier::STATUS_ASSIGNED,
            Courier::STATUS_DELIVERING,
        ], true);
        $isBusy = in_array($targetStatus, [
            Courier::STATUS_ASSIGNED,
            Courier::STATUS_DELIVERING,
        ], true);

        $sessionState = match ($targetStatus) {
            Courier::STATUS_ASSIGNED => self::SESSION_ASSIGNED,
            Courier::STATUS_DELIVERING => self::SESSION_IN_PROGRESS,
            Courier::STATUS_ONLINE => self::SESSION_READY,
            default => self::SESSION_OFFLINE,
        };

        return [
            'status' => $targetStatus,
            'active_order_status' => $activeOrderStatus,
            'has_active_order' => $activeOrderStatus !== null,
            'online' => $isOnline,
            'busy' => $isBusy,
            'session_state' => $sessionState,
            'status_repair_reason' => $statusRepairReason,
            'status_repair_source' => $statusRepairSource,
        ];
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
	


    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordPoofNotification($token));
    }

    /* =========================================================
     |  RELATIONS
     | ========================================================= */

    /**
     * Профиль клиента для API / web client surfaces.
     */
    public function clientProfile(): HasOne
    {
        return $this->hasOne(ClientProfile::class, 'user_id');
    }

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

    public function subscriptions(): HasMany
    {
        return $this->hasMany(ClientSubscription::class, 'client_id');
    }

    public function courierWithdrawalRequests(): HasMany
    {
        return $this->hasMany(CourierWithdrawalRequest::class, 'courier_id');
    }


    public function courierVerificationRequests(): HasMany
    {
        return $this->hasMany(CourierVerificationRequest::class, 'courier_id');
    }

    public function latestCourierVerificationRequest(): HasOne
    {
        return $this->hasOne(CourierVerificationRequest::class, 'courier_id')->latestOfMany();
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
