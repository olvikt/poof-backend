<?php

namespace App\Livewire\Client;

use Livewire\Component;

class ProfileForm extends Component
{
    public string $name = '';
    public ?string $phone = null;
    public string $email = '';

    protected $listeners = [
        "profile:open" => "loadUser",
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

		// 👇 ВАЖНО: обновляем модель пользователя в памяти
		auth()->setUser(auth()->user()->fresh());

		// закрываем sheet
		$this->dispatch('sheet:close');

		// событие для обновления профиля
		$this->dispatch('profile-saved');
	}

    public function render()
    {
        return view('livewire.client.profile-form');
    }
}