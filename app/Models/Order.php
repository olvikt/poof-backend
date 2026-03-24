<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Orders\Lifecycle\AcceptOrderByCourierAction;
use App\Actions\Orders\Lifecycle\CancelOrderAction;
use App\Actions\Orders\Lifecycle\CompleteOrderByCourierAction;
use App\Actions\Orders\Lifecycle\MarkOrderAsPaidAction;
use App\Actions\Orders\Lifecycle\StartOrderByCourierAction;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    app(MarkOrderAsPaidAction::class)->handle($this);
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

    public const CANONICAL_CREATE_COLUMNS = [
        'client_id',
        'status',
        'payment_status',
        'type',
        'service',
        'bags_count',
        'total_weight_kg',
        'price',
        'currency',
        'address_id',
        'address_text',
        'lat',
        'lng',
        'scheduled_date',
        'time_from',
        'time_to',
        'comment',
    ];

    public const LEGACY_WEB_CREATE_COLUMNS = [
        'client_id',
        'order_type',
        'status',
        'payment_status',
        'address_id',
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

    public const TEST_CREATE_COLUMNS = [
        'client_id',
        'courier_id',
        'status',
        'payment_status',
        'order_type',
        'bags_count',
        'price',
        'address_id',
        'address_text',
        'lat',
        'lng',
        'scheduled_date',
        'scheduled_time_from',
        'scheduled_time_to',
        'handover_type',
        'accepted_at',
        'started_at',
        'completed_at',
    ];

    /* =========================================================
     |  MASS ASSIGNMENT
     | ========================================================= */
    protected $guarded = ['*'];

    public static function createFromCanonicalContract(array $attributes): self
    {
        self::assertCreateBoundary($attributes, self::CANONICAL_CREATE_COLUMNS, 'canonical');

        return self::unguarded(fn () => self::query()->create($attributes));
    }

    public static function createFromLegacyWebContract(array $attributes): self
    {
        self::assertCreateBoundary($attributes, self::LEGACY_WEB_CREATE_COLUMNS, 'legacy-web');

        return self::unguarded(fn () => self::query()->create($attributes));
    }

    public static function createForTesting(array $attributes): self
    {
        if (! app()->runningUnitTests()) {
            throw new \LogicException('Order::createForTesting() is available only in automated tests.');
        }

        self::assertCreateBoundary($attributes, self::TEST_CREATE_COLUMNS, 'testing');

        return self::unguarded(fn () => self::query()->create($attributes));
    }

    private static function assertCreateBoundary(array $attributes, array $allowedColumns, string $contract): void
    {
        $unknownColumns = array_values(array_diff(array_keys($attributes), $allowedColumns));

        if ($unknownColumns === []) {
            return;
        }

        throw new MassAssignmentException(sprintf(
            'Unapproved %s order-create columns: %s',
            $contract,
            implode(', ', $unknownColumns),
        ));
    }

    /* =========================================================
     |  CASTS
     | ========================================================= */
    protected $casts = [
        'scheduled_date' => 'date',
        'lat'            => 'float',
        'lng'            => 'float',
        'bags_count'     => 'int',
        'total_weight_kg'=> 'float',
        'price'          => 'int',
        'is_trial'       => 'bool',
        'trial_days'     => 'int',

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


    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_NEW,
            self::STATUS_SEARCHING,
            self::STATUS_ACCEPTED,
        ], true);
    }

    /**
     * Прийняти замовлення курʼєром (атомарно)
     */
    public function acceptBy(User $courier): bool
    {
        return app(AcceptOrderByCourierAction::class)->handle($this, $courier);
    }

    /**
     * Почати виконання (курʼєр-safe)
     */
    public function startBy(User $courier): bool
    {
        return app(StartOrderByCourierAction::class)->handle($this, $courier);
    }

    /**
     * Завершити виконання (курʼєр-safe)
     */
    public function completeBy(User $courier): bool
    {
        return app(CompleteOrderByCourierAction::class)->handle($this, $courier);
    }

    /**
     * Backward compatible: старые вызовы (без курьера) — НЕ рекомендуются.
     * Оставлено, чтобы не ломать старые маршруты/формы.
     */
    public function start(): bool
    {
        $courier = $this->courier;

        if (! $courier instanceof User) {
            return false;
        }

        return $this->startBy($courier);
    }

    public function complete(): bool
    {
        $courier = $this->courier;

        if (! $courier instanceof User) {
            return false;
        }

        return $this->completeBy($courier);
    }

    public function cancel(): bool
    {
        return app(CancelOrderAction::class)->handle($this);
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

}
