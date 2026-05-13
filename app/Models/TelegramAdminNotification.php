<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramAdminNotification extends Model
{
    use HasFactory;

    public const STATUS_SENT = 'sent';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'admin_id',
        'courier_id',
        'notification_type',
        'status',
        'title',
        'message',
        'is_emergency',
        'telegram_error',
    ];
}
