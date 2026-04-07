<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderCompletionRequest extends Model
{
    public const POLICY_NONE = 'none';
    public const POLICY_DOOR_TWO_PHOTO_CLIENT_CONFIRM = 'door_two_photo_client_confirm';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_READY_FOR_SUBMIT = 'ready_for_submit';
    public const STATUS_AWAITING_CLIENT_CONFIRMATION = 'awaiting_client_confirmation';
    public const STATUS_CLIENT_CONFIRMED = 'client_confirmed';
    public const STATUS_DISPUTED = 'disputed';
    public const STATUS_AUTO_CONFIRMED = 'auto_confirmed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = ['*'];

    protected $casts = [
        'submitted_at' => 'datetime',
        'client_confirmed_at' => 'datetime',
        'auto_confirmation_due_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(OrderCompletionProof::class, 'completion_request_id');
    }
}
