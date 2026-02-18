<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class OrderOffer extends Model
{
    /* =========================================================
     |  STATUSES (FSM)
     | ========================================================= */

    public const STATUS_PENDING  = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_EXPIRED  = 'expired';

    /**
     * Финальные статусы — после них оффер не переиспользуется
     */
    public const FINAL_STATUSES = [
        self::STATUS_ACCEPTED,
        self::STATUS_DECLINED,
    ];

    /* =========================================================
     |  TYPES
     | ========================================================= */

    public const TYPE_PRIMARY = 'primary';
    public const TYPE_STACK   = 'stack';

    /* =========================================================
     |  MASS ASSIGNMENT
     | ========================================================= */

    protected $fillable = [
        'order_id',
        'courier_id',

        'type',              // primary | stack
        'parent_order_id',   // stack offer parent order
        'sequence',          // 1 | 2 | 3

        'status',

        'expires_at',        // TTL оффера
        'last_offered_at',   // cooldown контроль
    ];

    /* =========================================================
     |  CASTS
     | ========================================================= */

    protected $casts = [
        'expires_at'      => 'datetime',
        'last_offered_at' => 'datetime',
        'sequence'        => 'int',
    ];

    /* =========================================================
     |  RELATIONS
     | ========================================================= */

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function parentOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'parent_order_id');
    }

    /* =========================================================
     |  SCOPES (QUERY HELPERS)
     | ========================================================= */

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeFinal($query)
    {
        return $query->whereIn('status', self::FINAL_STATUSES);
    }

    /**
     * Pending + не истёк TTL
     */
    public function scopeAlive($query)
    {
        return $query
            ->where('status', self::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now());
    }

    /**
     * expired ИЛИ TTL прошёл
     */
    public function scopeExpired($query)
    {
        return $query->where(function ($q) {
            $q->where('status', self::STATUS_EXPIRED)
              ->orWhere(function ($q2) {
                  $q2->whereNotNull('expires_at')
                     ->where('expires_at', '<=', now());
              });
        });
    }

    /* =========================================================
     |  TYPE HELPERS (UI / LOGIC)
     | ========================================================= */

    public function isPrimary(): bool
    {
        return $this->type === self::TYPE_PRIMARY;
    }

    public function isStack(): bool
    {
        return $this->type === self::TYPE_STACK;
    }

    /* =========================================================
     |  STATE HELPERS
     | ========================================================= */

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL_STATUSES, true);
    }

    public function isAlive(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->expires_at instanceof Carbon
            && now()->lt($this->expires_at);
    }

    public function isExpired(): bool
    {
        if ($this->status === self::STATUS_EXPIRED) {
            return true;
        }

        return $this->expires_at instanceof Carbon
            && now()->gte($this->expires_at);
    }

    /**
     * Bolt / Uber: expired (без действия) можно показать снова
     */
    public function canBeReoffered(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    /* =========================================================
     |  COOLDOWN
     | ========================================================= */

    public function isOnCooldown(int $minutes): bool
    {
        return $this->last_offered_at instanceof Carbon
            && $this->last_offered_at->gt(now()->subMinutes($minutes));
    }

    public function touchLastOffered(): void
    {
        $this->update([
            'last_offered_at' => now(),
        ]);
    }

    /* =========================================================
     |  STATE TRANSITIONS (SAFE)
     | ========================================================= */

    /**
     * Истечение по TTL / scheduler
     */
    public function markExpired(): void
    {
        if (! $this->isPending()) {
            return;
        }

        $this->update([
            'status' => self::STATUS_EXPIRED,
        ]);
    }

    public function markDeclined(): void
    {
        if ($this->isFinal()) {
            return;
        }

        $this->update([
            'status' => self::STATUS_DECLINED,
        ]);
    }

    public function markAccepted(): void
    {
        if ($this->isFinal()) {
            return;
        }

        $this->update([
            'status' => self::STATUS_ACCEPTED,
        ]);
    }
}


