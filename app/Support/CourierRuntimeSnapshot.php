<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;

class CourierRuntimeSnapshot
{
    /**
     * Canonical compact courier runtime contract for web/livewire/js/api reads.
     */
    public static function fromUser(User $user): ?array
    {
        if (! $user->isCourier()) {
            return null;
        }

        $user->repairCourierRuntimeState();
        $user->refresh();

        $activeOrderStatus = $user->takenOrders()
            ->activeForCourier()
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [Order::STATUS_IN_PROGRESS])
            ->value('status');

        return [
            'online' => $user->isCourierOnline(),
            'busy' => (bool) $user->is_busy,
            'status' => (string) optional($user->courierProfile)->status,
            'session_state' => (string) $user->session_state,
            'active_order_status' => $activeOrderStatus,
            'has_active_order' => $activeOrderStatus !== null,
        ];
    }
}
