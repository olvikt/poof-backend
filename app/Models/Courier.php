<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Courier extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'rating',
        'completed_orders',
        'city',
        'transport',
        'is_verified',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'rating' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
