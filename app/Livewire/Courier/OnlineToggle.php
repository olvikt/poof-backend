<?php

namespace App\Livewire\Courier;

use Livewire\Component;
use App\Models\User;

class OnlineToggle extends Component
{
    public bool $online = false;

    public function mount(): void
    {
        $user = auth()->user();

        if ($user instanceof User && $user->isCourier()) {
            $this->online = $user->isCourierOnline();
        }
    }

    public function goOnline(): void
    {
        $user = auth()->user();

        if (! $user || ! $user->isCourier()) {
            return;
        }

        $user->goOnline();
        $this->online = true;

        $this->dispatch('courier-online-toggled');
        $this->dispatch('courier:online');
    }

    public function goOffline(): void
    {
        $user = auth()->user();

        if (! $user || ! $user->isCourier()) {
            return;
        }

        $user->goOffline();
        $this->online = false;

        $this->dispatch('courier-online-toggled');
        $this->dispatch('courier:offline');
    }

    public function render()
    {
        return view('livewire.courier.online-toggle');
    }
}