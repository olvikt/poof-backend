<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierOrderInterest extends Model
{
    public const STATUS_INTERESTED = 'interested';
    public const STATUS_WITHDRAWN = 'withdrawn';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_SELECTED = 'selected';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'order_id',
        'courier_id',
        'status',
        'expressed_at',
        'expires_at',
        'selected_at',
        'rejected_reason',
        'courier_lat',
        'courier_lng',
        'distance_meters',
        'eta_seconds',
    ];

    protected $casts = [
        'expressed_at' => 'datetime',
        'expires_at' => 'datetime',
        'selected_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }
}

