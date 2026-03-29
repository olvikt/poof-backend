<?php

namespace App\Livewire\Client;

use App\Actions\Profile\PersistClientProfileAction;
use App\DTO\Profile\ProfileFormData;
use Illuminate\Support\Facades\Log;
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
        Log::info('ui_save_flow_started', [
            'flow' => 'profile',
            'boundary' => 'before_persistence',
            'user_id' => auth()->id(),
        ]);

        try {
            $this->validate();

            $user = app(PersistClientProfileAction::class)->execute(
                auth()->user(),
                ProfileFormData::fromComponent($this),
            );

            auth()->setUser($user);

            Log::info('ui_save_flow_succeeded', [
                'flow' => 'profile',
                'boundary' => 'after_persistence',
                'user_id' => auth()->id(),
            ]);

            $this->dispatch('sheet:close', name: 'editProfile');
            $this->dispatch('profile-saved');
        } catch (\Throwable $e) {
            Log::error('ui_save_flow_failed', [
                'flow' => 'profile',
                'boundary' => 'after_persistence',
                'user_id' => auth()->id(),
                'failure_type' => 'exception',
                'exception_class' => $e::class,
            ]);

            throw $e;
        }
    }

    public function render()
    {
        return view('livewire.client.profile-form');
    }
}
