<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientSubscription extends Model
{
    protected $guarded = ['*'];

    protected $casts = [
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
        'paused_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'meta' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(ClientAddress::class, 'address_id');
    }

    public function generatedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'subscription_id');
    }
}
