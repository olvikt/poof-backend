<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Courier;
use App\Models\Order;
use App\Models\User;

class CourierRuntimeStateResolver
{
    /**
     * Resolve canonical courier runtime without performing writes.
     *
     * @return array<string,mixed>|null
     */
    public function resolveForUser(User $user): ?array
    {
        if (! $user->isCourier()) {
            return null;
        }

        $user->loadMissing('courierProfile');
        $courier = $user->courierProfile;

        if (! $courier) {
            return null;
        }

        $activeOrderStatus = $user->takenOrders()
            ->activeForCourier()
            ->orderByRaw("CASE WHEN status = ? THEN 0 ELSE 1 END", [Order::STATUS_IN_PROGRESS])
            ->value('status');

        return $this->resolveFromCourierStatus(
            courierStatus: (string) $courier->status,
            activeOrderStatus: $activeOrderStatus,
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function resolveFromCourierStatus(string $courierStatus, ?string $activeOrderStatus): array
    {
        $targetStatus = $courierStatus;
        $statusRepairReason = null;
        $statusRepairSource = null;

        if ($activeOrderStatus !== null) {
            $targetStatus = $activeOrderStatus === Order::STATUS_IN_PROGRESS
                ? Courier::STATUS_DELIVERING
                : Courier::STATUS_ASSIGNED;

            if ($courierStatus !== $targetStatus) {
                $statusRepairReason = 'active_order_enforced_status';
                $statusRepairSource = 'repair.active_order_enforce_status';
            }
        } else {
            if (! in_array($targetStatus, [
                Courier::STATUS_OFFLINE,
                Courier::STATUS_ONLINE,
                Courier::STATUS_ASSIGNED,
                Courier::STATUS_DELIVERING,
                Courier::STATUS_PAUSED,
            ], true)) {
                $targetStatus = Courier::STATUS_OFFLINE;
                $statusRepairReason = 'unknown_status';
                $statusRepairSource = 'repair.normalize_unknown_status';
            }

            if ($targetStatus === Courier::STATUS_PAUSED) {
                $targetStatus = Courier::STATUS_OFFLINE;
                $statusRepairReason = 'paused_normalized_to_offline';
                $statusRepairSource = 'repair.normalize_paused_status';
            }

            if (in_array($targetStatus, [Courier::STATUS_ASSIGNED, Courier::STATUS_DELIVERING], true)) {
                $targetStatus = Courier::STATUS_ONLINE;
                $statusRepairReason = 'orphan_busy_status_normalized_to_online';
                $statusRepairSource = 'repair.normalize_orphan_busy_status';
            }
        }

        $isOnline = in_array($targetStatus, [
            Courier::STATUS_ONLINE,
            Courier::STATUS_ASSIGNED,
            Courier::STATUS_DELIVERING,
        ], true);
        $isBusy = in_array($targetStatus, [
            Courier::STATUS_ASSIGNED,
            Courier::STATUS_DELIVERING,
        ], true);

        $sessionState = match ($targetStatus) {
            Courier::STATUS_ASSIGNED => User::SESSION_ASSIGNED,
            Courier::STATUS_DELIVERING => User::SESSION_IN_PROGRESS,
            Courier::STATUS_ONLINE => User::SESSION_READY,
            default => User::SESSION_OFFLINE,
        };

        return [
            'status' => $targetStatus,
            'active_order_status' => $activeOrderStatus,
            'has_active_order' => $activeOrderStatus !== null,
            'online' => $isOnline,
            'busy' => $isBusy,
            'session_state' => $sessionState,
            'status_repair_reason' => $statusRepairReason,
            'status_repair_source' => $statusRepairSource,
        ];
    }
}
