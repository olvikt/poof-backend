<?php

declare(strict_types=1);

namespace App\Livewire\Client;

use Livewire\Component;

class AddressesPage extends Component
{
    public bool $embedded = false;

    public function mount(bool $embedded = false): void
    {
        $this->embedded = $embedded;
    }

    public function render()
    {
        $view = view('livewire.client.addresses-page');

        return $this->embedded ? $view : $view->layout('layouts.client');
    }
}
