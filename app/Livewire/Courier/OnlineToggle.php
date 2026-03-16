<?php

namespace App\Livewire\Courier;

use Livewire\Component;
use App\Models\Courier;
use App\Models\Order;
use App\Models\User;

class OnlineToggle extends Component
{
    public bool $online = false;

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

        $this->online = $user instanceof User
            && $user->isCourier()
            && $user->isCourierOnline();
    }

    public function toggleOnlineState(): void
    {
        $user = $this->resolveCourier();

        if (! $user instanceof User) {
            return;
        }

        $before = $this->snapshotToggleState($user);
        $targetOnline = ! $before['online'];

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
        $courierStatus = (string) optional($user->courierProfile)->status;

        $activeOrderStatus = $user->takenOrders()
            ->activeForCourier()
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [Order::STATUS_IN_PROGRESS])
            ->value('status');

        return [
            'online' => $user->isCourierOnline(),
            'users_is_online' => (bool) $user->is_online,
            'users_is_busy' => (bool) $user->is_busy,
            'users_session_state' => (string) $user->session_state,
            'courier_status' => $courierStatus,
            'active_order_status' => $activeOrderStatus,
            'target_status' => $user->isCourierOnline() ? Courier::STATUS_OFFLINE : Courier::STATUS_ONLINE,
        ];
    }

    public function render()
    {
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
