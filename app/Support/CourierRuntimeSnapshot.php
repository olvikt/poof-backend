<?php

namespace App\Support;

use App\Models\Courier;
use App\Models\User;

class CourierRuntimeSnapshot
{
    /**
     * Stable snapshot keys consumed by Livewire/API/JS runtime.
     */
    public const CONTRACT_KEYS = [
        'online',
        'busy',
        'status',
        'session_state',
        'active_order_status',
        'has_active_order',
    ];

    /**
     * Fields that must come from backend runtime reconciliation.
     * UI may project these values, but must self-heal to backend snapshot.
     */
    public const BACKEND_CANONICAL_KEYS = self::CONTRACT_KEYS;

    /**
     * Canonical compact courier runtime contract for web/livewire/js/api reads.
     */
    public static function fromUser(User $user): ?array
    {
        if (! $user->isCourier()) {
            return null;
        }

        $runtime = app(CourierRuntimeStateResolver::class)->resolveForUser($user);

        if (! is_array($runtime)) {
            return null;
        }

        return [
            'online' => (bool) ($runtime['online'] ?? false),
            'busy' => (bool) ($runtime['busy'] ?? false),
            'status' => (string) ($runtime['status'] ?? Courier::STATUS_OFFLINE),
            'session_state' => (string) ($runtime['session_state'] ?? User::SESSION_OFFLINE),
            'active_order_status' => $runtime['active_order_status'] ?? null,
            'has_active_order' => (bool) ($runtime['has_active_order'] ?? false),
        ];
    }
}
