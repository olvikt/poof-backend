<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientAddress extends Model
{
    protected $fillable = [
		'user_id',          // 🔥 ОБЯЗАТЕЛЬНО
		'title',
		'address_text',
		'city',
		'street',
		'house',
		'entrance',
		'floor',
		'apartment',
		'intercom',
		'lat',
		'lng',
		'is_default',
	];

    public function clientProfile()
    {
        return $this->belongsTo(ClientProfile::class);
    }
}
