<?php

namespace App\Livewire\Client;

use Livewire\Component;
use Livewire\WithFileUploads;

class AvatarForm extends Component
{
    use WithFileUploads;

    public $photo;

    protected $rules = [
        'photo' => 'required|image|max:4096',
    ];

    public function save()
    {
        if (! $this->photo) {
            return;
        }

        $this->validate();

        // сохраняем файл
        $path = $this->photo->storePublicly('avatars', 'public');

        // обновляем пользователя
        auth()->user()->forceFill([
            'avatar' => $path,
        ])->save();

        // получаем НОВЫЙ url
        $avatarUrl = auth()->user()->avatar_url;

        // 🔥 В Livewire v3 это УЖЕ browser events
        $this->dispatch('avatar-saved', avatarUrl: $avatarUrl);
        $this->dispatch('sheet:close');

        // чистим состояние
        $this->reset('photo');
    }

    public function render()
    {
        return view('livewire.client.avatar-form');
    }
}



