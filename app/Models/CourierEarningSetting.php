<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierEarningSetting extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'global_commission_rate_percent' => 'decimal:2',
    ];

    public static function current(): self
    {
        return self::query()->firstOrCreate([], [
            'global_commission_rate_percent' => 20.00,
        ]);
    }
}
