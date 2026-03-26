<?php

namespace App\Livewire\Courier;

use Livewire\Component;
use App\Models\Courier;
use App\Models\User;

class OnlineToggle extends Component
{
    public bool $online = false;
    public bool $busyWithActiveOrder = false;
    public ?string $activeOrderStatus = null;

    public function mount(): void
    {
        $this->syncOnlineState();
    }

    public function hydrate(): void
    {
        $this->syncOnlineState();
    }

    public function syncOnlineState(): void
    {
        $user = $this->resolveCourier();
        $runtime = $user instanceof User ? $user->courierRuntimeSnapshot() : null;

        $this->online = (bool) ($runtime['online'] ?? false);
        $activeOrderStatus = $runtime['active_order_status'] ?? null;
        $this->activeOrderStatus = $activeOrderStatus;
        $this->busyWithActiveOrder = $activeOrderStatus !== null;
    }

    public function toggleOnlineState(): void
    {
        $user = $this->resolveCourier();

        if (! $user instanceof User) {
            return;
        }

        $before = $this->snapshotToggleState($user);

        $targetOnline = ! $before['online'];

        if (($before['active_order_status'] ?? null) !== null) {
            $this->online = true;
            $this->busyWithActiveOrder = true;
            $this->activeOrderStatus = (string) $before['active_order_status'];

            $this->dispatch(
                'courier-online-toggled',
                online: true,
                changed: false,
                attempted_online: $targetOnline,
                reason: 'blocked_by_active_order',
                before: $before,
                after: $before,
            );

            $this->dispatch(
                'courier-online-toggle-blocked',
                attempted_online: $targetOnline,
                reason: 'blocked_by_active_order',
                before: $before,
                after: $before,
            );

            return;
        }

        if ($before['online']) {
            $user->goOffline();
        } else {
            $user->goOnline();
        }

        $user = $this->resolveCourier();

        if (! $user instanceof User) {
            return;
        }

        $after = $this->snapshotToggleState($user);
        $this->online = $after['online'];
        $this->activeOrderStatus = $after['active_order_status'];
        $this->busyWithActiveOrder = $after['active_order_status'] !== null;

        $changed = $before['online'] !== $after['online'];
        $reason = $changed
            ? null
            : (($after['active_order_status'] ?? $before['active_order_status']) !== null
                ? 'blocked_by_active_order'
                : 'no_state_change');

        $this->dispatch(
            'courier-online-toggled',
            online: $this->online,
            changed: $changed,
            attempted_online: $targetOnline,
            reason: $reason,
            before: $before,
            after: $after,
        );

        if (! $changed) {
            $this->dispatch(
                'courier-online-toggle-blocked',
                attempted_online: $targetOnline,
                reason: $reason,
                before: $before,
                after: $after,
            );

            return;
        }

        if ($this->online) {
            $this->dispatch('courier:online');

            return;
        }

        $this->dispatch('courier:offline');
    }

    private function snapshotToggleState(User $user): array
    {
        $runtime = $user->courierRuntimeSnapshot();
        $activeOrderStatus = $runtime['active_order_status'] ?? null;

        return [
            'online' => (bool) ($runtime['online'] ?? false),
            'users_is_online' => (bool) $user->is_online,
            'users_is_busy' => (bool) $user->is_busy,
            'users_session_state' => (string) $user->session_state,
            'courier_status' => (string) ($runtime['status'] ?? optional($user->courierProfile)->status),
            'active_order_status' => $activeOrderStatus,
            'target_status' => ((bool) ($runtime['online'] ?? false)) ? Courier::STATUS_OFFLINE : Courier::STATUS_ONLINE,
        ];
    }

    public function render()
    {
        // Keep header status bound to canonical runtime state even when
        // Livewire navigation reuses/morphs DOM between courier tabs.
        $this->syncOnlineState();

        return view('livewire.courier.online-toggle');
    }

    private function resolveCourier(): ?User
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->isCourier()) {
            return null;
        }

        return $user->fresh(['courierProfile']);
    }
}
