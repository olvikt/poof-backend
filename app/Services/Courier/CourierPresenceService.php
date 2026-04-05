<?php

namespace App\Services\Courier;

use App\Models\Courier;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class CourierPresenceService
{
    public function resolveAuthenticatedCourier(): ?User
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->isCourier()) {
            return null;
        }

        return $user->fresh(['courierProfile']);
    }

    public function snapshot(?User $courier): ?array
    {
        return $courier instanceof User ? $courier->courierRuntimeSnapshot() : null;
    }

    /**
     * @return array{changed: bool, online: bool, before: array, after: array, reason: ?string, attempted_online: bool}
     */
    public function toggleOnline(User $courier): array
    {
        $before = $this->snapshotState($courier);
        $targetOnline = ! $before['online'];

        if (($before['active_order_status'] ?? null) !== null) {
            return [
                'changed' => false,
                'online' => true,
                'before' => $before,
                'after' => $before,
                'reason' => 'blocked_by_active_order',
                'attempted_online' => $targetOnline,
            ];
        }

        if ($before['online']) {
            $courier->goOffline();
        } else {
            $courier->goOnline();
        }

        $courier = $courier->fresh(['courierProfile']);
        $after = $this->snapshotState($courier);
        $changed = $before['online'] !== $after['online'];

        $result = [
            'changed' => $changed,
            'online' => $after['online'],
            'before' => $before,
            'after' => $after,
            'reason' => $changed
                ? null
                : (($after['active_order_status'] ?? $before['active_order_status']) !== null
                    ? 'blocked_by_active_order'
                    : 'no_state_change'),
            'attempted_online' => $targetOnline,
        ];

        Log::info('courier_presence_toggled', [
            'flow' => 'courier_presence',
            'courier_id' => $courier->id,
            'changed' => $result['changed'],
            'before' => $before,
            'after' => $after,
            'reason' => $result['reason'],
        ]);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotState(User $courier): array
    {
        $runtime = $courier->courierRuntimeSnapshot();
        $activeOrderStatus = $runtime['active_order_status'] ?? null;

        return [
            'online' => (bool) ($runtime['online'] ?? false),
            'users_is_online' => (bool) $courier->is_online,
            'users_is_busy' => (bool) $courier->is_busy,
            'users_session_state' => (string) $courier->session_state,
            'courier_status' => (string) ($runtime['status'] ?? optional($courier->courierProfile)->status),
            'active_order_status' => $activeOrderStatus,
            'target_status' => ((bool) ($runtime['online'] ?? false)) ? Courier::STATUS_OFFLINE : Courier::STATUS_ONLINE,
        ];
    }
}
