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
        $user = auth()->user();

        $this->online = $user instanceof User
            && $user->isCourier()
            && $user->isCourierOnline();
    }

    public function goOnline(): void
    {
        $user = auth()->user();

        if (! $user || ! $user->isCourier()) {
            return;
        }

        $user->goOnline();
        $this->syncOnlineState();

        $this->dispatch('courier-online-toggled', online: $this->online);
        $this->dispatch('courier:online');
    }

    public function goOffline(): void
    {
        $user = auth()->user();

        if (! $user || ! $user->isCourier()) {
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
}
