<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /* =========================================================
     |  PAYMENT STATUSES
     | ========================================================= */
    public const PAY_PENDING = 'pending';
    public const PAY_PAID    = 'paid';

    public const PAYMENT_LABELS = [
        self::PAY_PENDING => 'Очікує оплату',
        self::PAY_PAID    => 'Оплачено',
    ];

    /* =========================================================
     |  ORDER STATUSES
     | ========================================================= */
    public const STATUS_NEW          = 'new';        // створено, без оплати
    public const STATUS_SEARCHING    = 'searching';  // оплачен, шукаємо курʼєра
    public const STATUS_ACCEPTED     = 'accepted';   // курʼєр прийняв
    public const STATUS_IN_PROGRESS  = 'in_progress';
    public const STATUS_DONE         = 'done';
    public const STATUS_CANCELLED    = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_NEW          => 'Створено',
        self::STATUS_SEARCHING    => 'Шукаємо курʼєра',
        self::STATUS_ACCEPTED     => 'Курʼєр знайдений',
        self::STATUS_IN_PROGRESS  => 'Виконується',
        self::STATUS_DONE         => 'Виконано',
        self::STATUS_CANCELLED    => 'Скасовано',
    ];

    /* =========================================================
     |  PAYMENT DOMAIN LOGIC
     | ========================================================= */
public function markAsPaid(): void
{
    $this->update([
        'payment_status' => self::PAY_PAID,
        'status'         => self::STATUS_SEARCHING,
    ]);

    // 🚀 ТОЛЬКО событие
    event(new \App\Events\OrderCreated($this));
}

    /* =========================================================
     |  ORDER TYPES / OPTIONS
     | ========================================================= */
    public const TYPE_ONE_TIME     = 'one_time';
    public const TYPE_SUBSCRIPTION = 'subscription';

    public const HANDOVER_DOOR = 'door';
    public const HANDOVER_HAND = 'hand';

    public const HANDOVER_LABELS = [
        self::HANDOVER_DOOR => 'Забрати біля дверей',
        self::HANDOVER_HAND => 'Передача в руки',
    ];

    /* =========================================================
     |  MASS ASSIGNMENT
     | ========================================================= */
    protected $fillable = [
        'client_id',
        'courier_id',
        'order_type',
        'status',
        'payment_status',

        'address_text',
        'lat',
        'lng',
        'entrance',
        'floor',
        'apartment',
        'intercom',
        'comment',

        'scheduled_date',
        'scheduled_time_from',
        'scheduled_time_to',

        'handover_type',
        'bags_count',
        'price',

        'promo_code',
        'is_trial',
        'trial_days',

        // FSM timestamps (если колонок нет — добавь миграцию или убери отсюда)
        'accepted_at',
        'started_at',
        'completed_at',
    ];

    /* =========================================================
     |  CASTS
     | ========================================================= */
    protected $casts = [
        'scheduled_date' => 'date',
        'lat'            => 'float',
        'lng'            => 'float',
        'bags_count'     => 'int',
        'price'          => 'int',
        'is_trial'       => 'bool',
        'trial_days'     => 'int',

        // FSM timestamps
        'accepted_at'  => 'datetime',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    /* =========================================================
     |  RELATIONS
     | ========================================================= */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }
	
	public function offers(): HasMany
	{
		return $this->hasMany(\App\Models\OrderOffer::class, 'order_id');
	}
    /* =========================================================
     |  SCOPES
     | ========================================================= */
    public function scopeAvailableForCourier(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_SEARCHING)
            ->where('payment_status', self::PAY_PAID)
            ->whereNull('courier_id');
    }

    public function scopeActiveForCourier(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_ACCEPTED,
            self::STATUS_IN_PROGRESS,
        ]);
    }

    public function scopeActiveForClient(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_SEARCHING,
            self::STATUS_ACCEPTED,
            self::STATUS_IN_PROGRESS,
        ]);
    }

    /* =========================================================
     |  HELPERS
     | ========================================================= */
    public function isPaid(): bool
    {
        return $this->payment_status === self::PAY_PAID;
    }

    public function isTrial(): bool
    {
        return (bool) $this->is_trial;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_NEW,
            self::STATUS_SEARCHING,
            self::STATUS_ACCEPTED,
            self::STATUS_IN_PROGRESS,
        ], true);
    }

    /* =========================================================
     |  TIME SLOTS & PRICING
     | ========================================================= */
    public static function allowedTimeSlots(): array
    {
        return [
            ['08:00', '10:00'],
            ['10:00', '12:00'],
            ['12:00', '14:00'],
            ['14:00', '16:00'],
            ['16:00', '18:00'],
            ['18:00', '20:00'],
            ['20:00', '23:00'],
        ];
    }

    public static function bagsPricing(): array
    {
        return [
            1 => 40,
            2 => 55,
            3 => 70,
        ];
    }

    public static function calcPriceByBags(int $bags): int
    {
        return self::bagsPricing()[$bags] ?? self::bagsPricing()[1];
    }

    /* =========================================================
     |  COURIER DOMAIN LOGIC (STRICT)
     | ========================================================= */

    public function canBeAccepted(): bool
    {
        return $this->status === self::STATUS_SEARCHING
            && $this->courier_id === null
            && $this->payment_status === self::PAY_PAID;
    }

    public function canBeStartedBy(User $courier): bool
    {
        return $this->status === self::STATUS_ACCEPTED
            && (int) $this->courier_id === (int) $courier->id;
    }

    public function canBeCompletedBy(User $courier): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS
            && (int) $this->courier_id === (int) $courier->id;
    }

    /**
     * Прийняти замовлення курʼєром (атомарно)
     */
public function acceptBy(User $courier): bool
{
    return (bool) DB::transaction(function () use ($courier) {

        $order = self::query()
            ->whereKey($this->getKey())
            ->lockForUpdate()
            ->first();

        if (! $order || ! $order->canBeAccepted()) {
            return false;
        }

        if (method_exists($courier, 'canAcceptOrders') && ! $courier->canAcceptOrders()) {
            return false;
        }

        // 1️⃣ Назначаем заказ
        $order->update([
            'status'      => self::STATUS_ACCEPTED,
            'courier_id'  => $courier->id,
            'accepted_at' => now(),
        ]);

        // 2️⃣ Курьер становится busy
        if (method_exists($courier, 'markBusy')) {
            $courier->markBusy();
        }

        $courier->courierProfile()->update([
            'status' => Courier::STATUS_ASSIGNED,
        ]);

        // 🔥 3️⃣ ВАЖНО: убиваем все остальные pending этого курьера
        \App\Models\OrderOffer::where('courier_id', $courier->id)
            ->where('status', \App\Models\OrderOffer::STATUS_PENDING)
            ->where('order_id', '!=', $order->id)
            ->update([
                'status' => \App\Models\OrderOffer::STATUS_EXPIRED,
            ]);

        return true;
    });
}

    /**
     * Почати виконання (курʼєр-safe)
     */
    public function startBy(User $courier): bool
    {
        return (bool) DB::transaction(function () use ($courier) {
            $order = self::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->first();

            if (! $order || ! $order->canBeStartedBy($courier)) {
                return false;
            }

            $order->update([
                'status'     => self::STATUS_IN_PROGRESS,
                'started_at' => now(),
            ]);

            $courier->courierProfile()->update([
                'status' => Courier::STATUS_DELIVERING,
            ]);

            return true;
        });
    }

    /**
     * Завершити виконання (курʼєр-safe)
     */
    public function completeBy(User $courier): bool
    {
        return (bool) DB::transaction(function () use ($courier) {
            $order = self::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->first();

            if (! $order || ! $order->canBeCompletedBy($courier)) {
                return false;
            }

            $order->update([
                'status'       => self::STATUS_DONE,
                'completed_at' => now(),
            ]);			
			

            if (method_exists($courier, 'markFree')) {
                $courier->markFree();
            }

            $courier->courierProfile()->update([
                'status' => Courier::STATUS_ONLINE,
            ]);
			
			$courier->update([
				'last_completed_at' => now(),
			]);

            return true;
        });
    }

    /**
     * Backward compatible: старые вызовы (без курьера) — НЕ рекомендуются.
     * Оставлено, чтобы не ломать старые маршруты/формы.
     */
    public function start(): bool
    {
        // Безопасно разрешаем только если заказ уже принят (но не защищает от чужого курьера)
        return $this->status === self::STATUS_ACCEPTED
            && $this->update([
                'status'     => self::STATUS_IN_PROGRESS,
                'started_at' => $this->started_at ?? now(),
            ]);
    }

    public function complete(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS
            && $this->update([
                'status'       => self::STATUS_DONE,
                'completed_at' => $this->completed_at ?? now(),
            ]);
    }

    public function cancel(): bool
    {
        return in_array($this->status, [
            self::STATUS_NEW,
            self::STATUS_SEARCHING,
        ], true) && $this->update(['status' => self::STATUS_CANCELLED]);
    }

    /* =========================================================
     |  UI HELPERS (old API preserved)
     | ========================================================= */
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
        return in_array($this->status, [
            self::STATUS_NEW,
            self::STATUS_SEARCHING,
            self::STATUS_ACCEPTED,
        ], true);
    }
}
