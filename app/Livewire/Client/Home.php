<?php

namespace App\Livewire\Client;

use Livewire\Component;

class Home extends Component
{
    public int $ordersCount = 0;
    public int $addressesCount = 0;
    public string $appVersion = '1.0.0';

    public function mount(): void
    {
        $user = auth()->user();

        $this->ordersCount = (int) $user->orders()->count();
        $this->addressesCount = (int) $user->addresses()->count();

        $this->appVersion = (string) (
            config('app.version')
            ?? config('app.app_version')
            ?? env('APP_VERSION')
            ?? '1.0.0'
        );
    }

    public function render()
    {
        return view('livewire.client.home')
            ->layout('layouts.client');
    }
}
