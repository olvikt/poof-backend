<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class ClientProfile extends Model
{
    private const CLIENT_UPDATE_FIELDS = [
        'name',
        'push_notifications',
        'email_notifications',
    ];

    protected $fillable = [
        'user_id',
        'name',
        'bonuses',
        'push_notifications',
        'email_notifications',
    ];

    public function updateFromClient(array $attributes): void
    {
        $this->fill(Arr::only($attributes, self::CLIENT_UPDATE_FIELDS));
        $this->save();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function addresses()
    {
        return $this->hasMany(ClientAddress::class, 'user_id', 'user_id');
    }
}
