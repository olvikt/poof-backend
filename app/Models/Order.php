<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

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
    }

    /* =========================================================
     |  ORDER TYPES / OPTIONS
     | ========================================================= */
    public const TYPE_ONE_TIME     = 'one_time';
    public const TYPE_SUBSCRIPTION = 'subscription';

    public const HANDOVER_DOOR = 'door';
    public const HANDOVER_HAND = 'hand';

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
     |  COURIER DOMAIN LOGIC
     | ========================================================= */
    public function canBeAccepted(): bool
    {
        return $this->status === self::STATUS_SEARCHING
            && $this->courier_id === null;
    }

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

            $order->update([
                'status'     => self::STATUS_ACCEPTED,
                'courier_id' => $courier->id,
            ]);

            return true;
        });
    }

    public function start(): bool
    {
        return $this->status === self::STATUS_ACCEPTED
            && $this->update(['status' => self::STATUS_IN_PROGRESS]);
    }

    public function complete(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS
            && $this->update(['status' => self::STATUS_DONE]);
    }

    public function cancel(): bool
    {
        return in_array($this->status, [
            self::STATUS_NEW,
            self::STATUS_SEARCHING,
        ], true) && $this->update(['status' => self::STATUS_CANCELLED]);
    }

    /* =========================================================
     |  ADMIN / COURIER STATE HELPERS
     | ========================================================= */
    public function canBeStarted(): bool
    {
        // Курьер может начать, если заказ принят
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function canBeCompleted(): bool
    {
        // Завершить можно только в процессе
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function canBeCancelled(): bool
    {
        // Отменить можно до выполнения
        return in_array($this->status, [
            self::STATUS_NEW,
            self::STATUS_SEARCHING,
            self::STATUS_ACCEPTED,
        ], true);
    }
}
