<?php

namespace App\Livewire\Client;

use App\Actions\Profile\PersistClientProfile;
use App\DTO\Profile\ProfileFormData;
use Livewire\Component;

class ProfileForm extends Component
{
    public string $name = '';
    public ?string $phone = null;
    public string $email = '';

    protected $listeners = [
        'profile:open' => 'loadUser',
    ];

    protected $rules = [
        'name' => 'required|string|min:2',
        'phone' => 'nullable|string',
        'email' => 'required|email',
    ];

    public function mount(): void
    {
        $this->loadUser();
    }

    public function loadUser(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $this->name = (string) $user->name;
        $this->phone = $user->phone;
        $this->email = (string) $user->email;
    }

    public function save(): void
    {
        $this->validate();

        $user = app(PersistClientProfile::class)->execute(
            auth()->user(),
            ProfileFormData::fromComponent($this),
        );

        auth()->setUser($user);

        $this->dispatch('sheet:close', name: 'editProfile');
        $this->dispatch('profile-saved');
    }

    public function render()
    {
        return view('livewire.client.profile-form');
    }
}
