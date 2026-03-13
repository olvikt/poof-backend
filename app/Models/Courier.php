<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Courier extends Model
{
    public const STATUS_OFFLINE = 'offline';
    public const STATUS_ONLINE = 'online';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_DELIVERING = 'delivering';
    public const STATUS_PAUSED = 'paused';

    public const ACTIVE_MAP_STATUSES = [
        self::STATUS_ONLINE,
        self::STATUS_ASSIGNED,
        self::STATUS_DELIVERING,
    ];

    protected $fillable = [
        'user_id',
        'status',
        'last_location_at',
        'rating',
        'completed_orders',
        'city',
        'transport',
        'transport_type',
        'is_verified',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'rating' => 'float',
        'last_location_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActiveOnMap(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_MAP_STATUSES)
            ->where('last_location_at', '>', now()->subSeconds(60));
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ONLINE);
    }

    public function scopeBusy(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_ASSIGNED,
            self::STATUS_DELIVERING,
        ]);
    }
}
