<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierEarning extends Model
{
    public const STATUS_SETTLED = 'settled';

    protected $guarded = ['id'];

    protected $casts = [
        'gross_amount' => 'int',
        'commission_rate_percent' => 'decimal:2',
        'commission_amount' => 'int',
        'net_amount' => 'int',
        'bonuses_amount' => 'int',
        'penalties_amount' => 'int',
        'adjustments_amount' => 'int',
        'settled_at' => 'datetime',
    ];

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
