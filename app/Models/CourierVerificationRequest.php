<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierVerificationRequest extends Model
{
    use HasFactory;

    public const DOCUMENT_TYPE_PASSPORT = 'passport';
    public const DOCUMENT_TYPE_ID_CARD = 'id_card';

    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'courier_id',
        'document_type',
        'status',
        'document_file_path',
        'document_file_disk',
        'document_mime_type',
        'document_file_size_bytes',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'document_file_size_bytes' => 'integer',
        ];
    }

    public static function allowedDocumentTypes(): array
    {
        return [
            self::DOCUMENT_TYPE_PASSPORT,
            self::DOCUMENT_TYPE_ID_CARD,
        ];
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPendingReview(): bool
    {
        return $this->status === self::STATUS_PENDING_REVIEW;
    }
}
