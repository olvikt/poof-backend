<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientSubscription extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_COMPLETED = 'completed';
    public const BILLING_UNPAID = 'unpaid';
    public const BILLING_PAID = 'paid';
    public const BILLING_PAYMENT_FAILED = 'payment_failed';
    public const BILLING_RENEWAL_DUE = 'renewal_due';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Чернетка',
        self::STATUS_UNPAID => 'Не оплачена',
        self::STATUS_ACTIVE => 'Активна',
        self::STATUS_PAUSED => 'На паузі',
        self::STATUS_CANCELLED => 'Скасована',
        self::STATUS_COMPLETED => 'Завершена',
    ];

    protected $guarded = ['*'];

    protected $casts = [
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
        'ends_at' => 'datetime',
        'auto_renew' => 'bool',
        'renewals_count' => 'int',
        'paused_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'meta' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(ClientAddress::class, 'address_id');
    }

    public function generatedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'subscription_id');
    }
    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->display_status] ?? (string) $this->display_status;
    }

    public function getDisplayStatusAttribute(): string
    {
        if ($this->lifecycle_state === self::STATUS_CANCELLED) {
            return self::STATUS_CANCELLED;
        }

        if ($this->lifecycle_state === self::STATUS_COMPLETED) {
            return self::STATUS_COMPLETED;
        }

        if ($this->lifecycle_state === self::STATUS_PAUSED) {
            return self::STATUS_PAUSED;
        }

        if ($this->billing_state === self::BILLING_UNPAID) {
            return self::STATUS_UNPAID;
        }

        return self::STATUS_ACTIVE;
    }

    public function getLifecycleStateAttribute(): string
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return self::STATUS_CANCELLED;
        }

        if ($this->ends_at !== null && $this->ends_at->isPast() && $this->status !== self::STATUS_CANCELLED) {
            return self::STATUS_COMPLETED;
        }

        if ($this->status === self::STATUS_PAUSED) {
            return self::STATUS_PAUSED;
        }

        return self::STATUS_ACTIVE;
    }

    public function getBillingStateAttribute(): string
    {
        if (! $this->hasPaidOrders()) {
            return self::BILLING_UNPAID;
        }

        if ($this->ends_at !== null && $this->ends_at->isPast() && (bool) $this->auto_renew) {
            return self::BILLING_RENEWAL_DUE;
        }

        return self::BILLING_PAID;
    }

    public function getStatusBadgeClassesAttribute(): string
    {
        if ($this->billing_state === self::BILLING_RENEWAL_DUE && $this->display_status === self::STATUS_ACTIVE) {
            return 'bg-blue-500/20 text-blue-200';
        }

        return match ($this->display_status) {
            self::STATUS_ACTIVE => 'bg-green-500/20 text-green-300',
            self::STATUS_UNPAID, self::STATUS_PAUSED => 'bg-yellow-500/20 text-yellow-300',
            self::STATUS_CANCELLED, self::STATUS_COMPLETED => 'bg-gray-700 text-gray-300',
            default => 'bg-gray-700 text-gray-300',
        };
    }

    public function getUiStatusLabelAttribute(): string
    {
        if ($this->billing_state === self::BILLING_RENEWAL_DUE && $this->display_status === self::STATUS_ACTIVE) {
            return 'Очікує продовження';
        }

        return $this->status_label;
    }

    public function hasPaidOrders(): bool
    {
        if (array_key_exists('paid_orders_count', $this->attributes)) {
            return (int) $this->attributes['paid_orders_count'] > 0;
        }

        return $this->generatedOrders()
            ->where('payment_status', Order::PAY_PAID)
            ->exists();
    }

    public function isUnpaid(): bool
    {
        return $this->billing_state === self::BILLING_UNPAID;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isCompleted(): bool
    {
        return $this->lifecycle_state === self::STATUS_COMPLETED;
    }

    public function canPause(): bool
    {
        return $this->display_status === self::STATUS_ACTIVE && $this->billing_state === self::BILLING_PAID;
    }

    public function canResume(): bool
    {
        return $this->display_status === self::STATUS_PAUSED;
    }

    public function canCancel(): bool
    {
        return $this->billing_state === self::BILLING_UNPAID
            && ! in_array($this->display_status, [self::STATUS_CANCELLED, self::STATUS_COMPLETED], true);
    }

    public function canRenew(): bool
    {
        return $this->billing_state === self::BILLING_PAID
            && in_array($this->display_status, [self::STATUS_ACTIVE, self::STATUS_COMPLETED], true);
    }

    public function canPay(): bool
    {
        return $this->billing_state === self::BILLING_UNPAID;
    }

    public function canToggleAutoRenew(): bool
    {
        return $this->billing_state === self::BILLING_PAID
            && ! in_array($this->lifecycle_state, [self::STATUS_CANCELLED], true);
    }

    public function startsAtForDisplay(): ?\Illuminate\Support\Carbon
    {
        if ($this->billing_state === self::BILLING_UNPAID) {
            return null;
        }

        $firstPaidOrder = $this->generatedOrders()
            ->where('payment_status', Order::PAY_PAID)
            ->orderBy('created_at')
            ->first();

        return $firstPaidOrder?->created_at;
    }

    public function activeUntilForDisplay(): ?\Illuminate\Support\Carbon
    {
        return $this->billing_state === self::BILLING_UNPAID ? null : $this->ends_at;
    }

    public function canOpenDetails(): bool
    {
        return $this->billing_state === self::BILLING_PAID;
    }

    public function canGenerateNextOrderAutomatically(): bool
    {
        return $this->lifecycle_state === self::STATUS_ACTIVE
            && $this->billing_state === self::BILLING_PAID
            && (bool) $this->auto_renew;
    }

    public function getFrequencyLabelAttribute(): string
    {
        return match ((string) ($this->plan?->frequency_type ?? $this->meta['frequency_type'] ?? '')) {
            'daily' => 'Щодня',
            'every_2_days' => '1 раз в 2 дні',
            'every_3_days' => '1 раз в 3 дні',
            default => 'За графіком',
        };
    }

}
