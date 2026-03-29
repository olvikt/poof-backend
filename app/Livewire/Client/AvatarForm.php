<?php

namespace App\Livewire\Client;

use App\Actions\Avatar\PersistClientAvatarAction;
use App\DTO\Avatar\AvatarUploadData;
use Livewire\Component;
use Livewire\WithFileUploads;

class AvatarForm extends Component
{
    use WithFileUploads;

    public $avatar;

    public function save(): void
    {
        if (! $this->avatar) {
            return;
        }

        $this->validate([
            'avatar' => 'image|max:2048',
        ]);

        $user = app(PersistClientAvatarAction::class)->execute(
            auth()->user(),
            new AvatarUploadData($this->avatar),
        );

        $this->dispatch('avatar-saved', avatarUrl: $user->avatar_url);
        $this->dispatch('sheet:close', name: 'editAvatar');

        $this->reset('avatar');
    }

    public function render()
    {
        return view('livewire.client.avatar-form');
    }
}
