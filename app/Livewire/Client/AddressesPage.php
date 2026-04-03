<?php

declare(strict_types=1);

namespace App\Livewire\Client;

use Livewire\Component;

class AddressesPage extends Component
{
    public function render()
    {
        return view('livewire.client.addresses-page')
            ->layout('layouts.client');
    }
}
