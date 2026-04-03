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

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Чернетка',
        self::STATUS_ACTIVE => 'Активна',
        self::STATUS_PAUSED => 'На паузі',
        self::STATUS_CANCELLED => 'Зупинена',
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
        return self::STATUS_LABELS[$this->status] ?? (string) $this->status;
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

