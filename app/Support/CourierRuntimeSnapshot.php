<?php

namespace App\Support;

use App\Models\Courier;
use App\Models\Order;
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

        $user->repairCourierRuntimeState();
        $user->loadMissing('courierProfile');

        $status = (string) optional($user->courierProfile)->status;
        $activeOrderStatus = match ($status) {
            Courier::STATUS_DELIVERING => Order::STATUS_IN_PROGRESS,
            Courier::STATUS_ASSIGNED => Order::STATUS_ACCEPTED,
            default => null,
        };

        return [
            'online' => in_array($status, [
                Courier::STATUS_ONLINE,
                Courier::STATUS_ASSIGNED,
                Courier::STATUS_DELIVERING,
            ], true),
            'busy' => in_array($status, [
                Courier::STATUS_ASSIGNED,
                Courier::STATUS_DELIVERING,
            ], true),
            'status' => $status,
            'session_state' => (string) $user->session_state,
            'active_order_status' => $activeOrderStatus,
            'has_active_order' => $activeOrderStatus !== null,
        ];
    }
}
