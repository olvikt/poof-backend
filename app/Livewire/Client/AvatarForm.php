<?php

namespace App\Livewire\Client;

use Livewire\Component;
use Livewire\WithFileUploads;

class AvatarForm extends Component
{
    use WithFileUploads;

    public $avatar;

    public function save()
    {
        if (! $this->avatar) {
            return;
        }

        $this->validate([
            'avatar' => 'image|max:2048',
        ]);

        $path = $this->avatar->store('avatars', 'public');

        auth()->user()->update([
            'avatar' => $path,
        ]);

        $avatarUrl = auth()->user()->fresh()->avatar_url;

        $this->dispatch('avatar-saved', avatarUrl: $avatarUrl);
        $this->dispatch('sheet:close');

        $this->reset('avatar');
    }

    public function render()
    {
        return view('livewire.client.avatar-form');
    }
}
