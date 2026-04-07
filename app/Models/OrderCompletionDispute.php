<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderCompletionDispute extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_RESOLVED_CONFIRMED = 'resolved_confirmed';
    public const STATUS_RESOLVED_REJECTED = 'resolved_rejected';

    protected $guarded = ['*'];

    protected $casts = [
        'opened_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(OrderCompletionRequest::class, 'completion_request_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolver_user_id');
    }
}
