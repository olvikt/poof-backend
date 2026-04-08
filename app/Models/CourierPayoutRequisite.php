<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierPayoutRequisite extends Model
{
    protected $fillable = [
        'courier_id',
        'card_holder_name',
        'card_number_encrypted',
        'masked_card_number',
        'bank_name',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'card_number_encrypted' => 'encrypted',
        ];
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }
}
