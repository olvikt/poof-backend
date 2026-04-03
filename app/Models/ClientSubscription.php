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

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Чернетка',
        self::STATUS_UNPAID => 'Не оплачена',
        self::STATUS_ACTIVE => 'Активна',
        self::STATUS_PAUSED => 'На паузі',
        self::STATUS_CANCELLED => 'Зупинена',
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
        if ($this->isCancelled()) {
            return self::STATUS_CANCELLED;
        }

        if ($this->isCompleted()) {
            return self::STATUS_COMPLETED;
        }

        if ($this->status === self::STATUS_PAUSED) {
            return self::STATUS_PAUSED;
        }

        if ($this->isUnpaid()) {
            return self::STATUS_UNPAID;
        }

        return self::STATUS_ACTIVE;
    }

    public function getStatusBadgeClassesAttribute(): string
    {
        return match ($this->display_status) {
            self::STATUS_ACTIVE => 'bg-green-500/20 text-green-300',
            self::STATUS_UNPAID, self::STATUS_PAUSED => 'bg-yellow-500/20 text-yellow-300',
            self::STATUS_CANCELLED, self::STATUS_COMPLETED => 'bg-gray-700 text-gray-300',
            default => 'bg-gray-700 text-gray-300',
        };
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
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_ACTIVE], true) && ! $this->hasPaidOrders();
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isCompleted(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isPast() && ! $this->isCancelled();
    }

    public function canPause(): bool
    {
        return $this->display_status === self::STATUS_ACTIVE;
    }

    public function canResume(): bool
    {
        return $this->display_status === self::STATUS_PAUSED;
    }

    public function canCancel(): bool
    {
        return ! in_array($this->display_status, [self::STATUS_CANCELLED, self::STATUS_COMPLETED], true);
    }

    public function canRenew(): bool
    {
        return in_array($this->display_status, [self::STATUS_ACTIVE, self::STATUS_PAUSED], true);
    }

    public function canPay(): bool
    {
        return $this->display_status === self::STATUS_UNPAID;
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
