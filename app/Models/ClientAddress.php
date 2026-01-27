<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAddress extends Model
{
    protected $fillable = [
        'user_id',

        // тип адреса
        'label',        // home, work, custom
        'title',        // "Дім", "Робота", "Мама"

        // адрес
        'address',      // улица + дом
        'city',
        'entrance',
        'floor',
        'apartment',

        // координаты (на будущее)
        'lat',
        'lng',

        // флаги
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* =========================
     |  HELPERS
     | ========================= */

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->entrance ? 'підʼїзд '.$this->entrance : null,
            $this->floor ? 'поверх '.$this->floor : null,
            $this->apartment ? 'кв. '.$this->apartment : null,
        ]);

        return implode(', ', $parts);
    }
}
