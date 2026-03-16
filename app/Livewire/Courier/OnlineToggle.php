<?php

namespace App\Livewire\Courier;

use Livewire\Component;
use App\Models\User;

class OnlineToggle extends Component
{
    public bool $online = false;

    protected $listeners = [
        'courier-online-sync-requested' => 'syncOnlineState',
    ];

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

    public function goOnline(): void
    {
        $user = $this->resolveCourier();

        if (! $user instanceof User) {
            return;
        }

        $user->goOnline();
        $this->syncOnlineState();

        $this->dispatch('courier-online-toggled', online: $this->online);
        $this->dispatch('courier:online');
    }

    public function toggleOnlineState(): void
    {
        if ($this->online) {
            $this->goOffline();

            return;
        }

        $this->goOnline();
    }

    public function goOffline(): void
    {
        $user = $this->resolveCourier();

        if (! $user instanceof User) {
            return;
        }

        $user->goOffline();
        $this->syncOnlineState();

        $this->dispatch('courier-online-toggled', online: $this->online);
        $this->dispatch('courier:offline');
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
