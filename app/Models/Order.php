<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Class Order
 *
 * Логика:
 * - Клиент создаёт заказ -> status=new, courier_id=NULL
 * - Курьер видит пул доступных заказов (new + courier_id NULL)
 * - Курьер принимает заказ атомарно -> status=accepted, courier_id=<id>
 * - Далее: in_progress -> done / cancelled
 */
class Order extends Model
{
    /* =========================================================
     |  STATUS CONSTANTS
     | ========================================================= */

    public const STATUS_NEW         = 'new';
    public const STATUS_ACCEPTED    = 'accepted';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE        = 'done';
    public const STATUS_CANCELLED   = 'cancelled';
    public const STATUS_EXPIRED     = 'expired';

    /**
     * Список допустимых статусов (для валидации/форм/таблиц).
     */
    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_ACCEPTED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_DONE,
        self::STATUS_CANCELLED,
        self::STATUS_EXPIRED,
    ];

    /**
     * Человекочитаемые названия (удобно для Filament).
     */
    public const STATUS_LABELS = [
        self::STATUS_NEW         => 'Нове',
        self::STATUS_ACCEPTED    => 'Прийняте',
        self::STATUS_IN_PROGRESS => 'У процесі',
        self::STATUS_DONE        => 'Виконано',
        self::STATUS_CANCELLED   => 'Скасовано',
        self::STATUS_EXPIRED     => 'Термін дії минув',
    ];

    /* =========================================================
     |  MASS ASSIGNMENT
     | ========================================================= */

    protected $fillable = [
        'client_id',
        'courier_id',      // NULL до принятия
        'status',
        'service',         // trash_removal и т.д.
        'price',
        'currency',
        'address',
        'comment',
        'scheduled_at',
        'lat',
        'lng',
        'zone_id',
    ];

    /* =========================================================
     |  CASTS
     | ========================================================= */

    protected $casts = [
        'scheduled_at' => 'datetime',
        'price'        => 'float',
        'lat'          => 'float',
        'lng'          => 'float',
    ];

    /* =========================================================
     |  RELATIONS
     | ========================================================= */

    /**
     * Клиент, создавший заказ.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Курьер, который принял заказ (NULL до принятия).
     */
    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    /**
     * Район / зона (для карты / фильтрации).
     * Если модели Zone ещё нет — можешь временно удалить этот relation.
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    /* =========================================================
     |  SCOPES
     | ========================================================= */

    /**
     * Доступные заказы для курьеров: новые и ещё никем не принятые.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_NEW)
            ->whereNull('courier_id');
    }

    /**
     * Активные (в работе): принятые или в процессе.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_ACCEPTED,
            self::STATUS_IN_PROGRESS,
        ]);
    }

    /* =========================================================
     |  HELPERS
     | ========================================================= */

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_NEW && $this->courier_id === null;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_ACCEPTED, self::STATUS_IN_PROGRESS], true);
    }

    public function canBeAccepted(): bool
    {
        return $this->isAvailable();
    }

    public function canBeStarted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function canBeCompleted(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_NEW, self::STATUS_ACCEPTED], true);
    }

    /* =========================================================
     |  DOMAIN LOGIC
     | ========================================================= */

    /**
     * Курьер принимает заказ атомарно (защита от одновременного принятия).
     *
     * Возвращает true, если заказ был успешно принят текущим курьером.
     */
    public function acceptBy(User $courier): bool
    {
        if (! $courier->isCourier()) {
            return false;
        }

        return (bool) DB::transaction(function () use ($courier) {
            /** @var self|null $order */
            $order = self::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->first();

            if (! $order || ! $order->canBeAccepted()) {
                return false;
            }

            // Важно: обновляем именно "залоченный" экземпляр $order
            $order->status = self::STATUS_ACCEPTED;
            $order->courier_id = $courier->id;

            return $order->save();
        });
    }

    /**
     * Перевести заказ в "in_progress".
     */
    public function start(): bool
    {
        if (! $this->canBeStarted()) {
            return false;
        }

        return $this->update([
            'status' => self::STATUS_IN_PROGRESS,
        ]);
    }

    /**
     * Завершить заказ.
     */
    public function complete(): bool
    {
        if (! $this->canBeCompleted()) {
            return false;
        }

        return $this->update([
            'status' => self::STATUS_DONE,
        ]);
    }

    /**
     * Отменить заказ (клиентом/админом по правилам).
     */
    public function cancel(): bool
    {
        if (! $this->canBeCancelled()) {
            return false;
        }

        return $this->update([
            'status' => self::STATUS_CANCELLED,
        ]);
    }
}
