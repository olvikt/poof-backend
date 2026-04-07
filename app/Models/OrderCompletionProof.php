<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderCompletionProof extends Model
{
    public const TYPE_DOOR_PHOTO = 'door_photo';
    public const TYPE_CONTAINER_PHOTO = 'container_photo';

    protected $guarded = ['*'];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'client_device_clock_at' => 'datetime',
        'file_size_bytes' => 'int',
    ];

    public function completionRequest(): BelongsTo
    {
        return $this->belongsTo(OrderCompletionRequest::class, 'completion_request_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }
}
