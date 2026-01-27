<?php

namespace App\Livewire\Client;

use Livewire\Component;

class ProfileForm extends Component
{
    public string $name = '';
    public ?string $phone = null;
    public string $email = '';

    public function mount()
    {
        $user = auth()->user();

        $this->name  = $user->name;
        $this->phone = $user->phone;
        $this->email = $user->email;
    }

    protected $rules = [
        'name'  => 'required|string|min:2',
        'phone' => 'nullable|string',
        'email' => 'required|email',
    ];

    public function save()
    {
        $this->validate();

        auth()->user()->update([
            'name'  => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
        ]);

        // закрываем sheet
        $this->dispatch('sheet:close');

        // (опционально) событие для UI
        $this->dispatch('profile-saved');
    }

    public function render()
    {
        return view('livewire.client.profile-form');
    }
}