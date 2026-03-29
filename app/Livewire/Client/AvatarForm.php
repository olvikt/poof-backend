<?php

namespace App\Livewire\Client;

use App\Actions\Avatar\PersistClientAvatarAction;
use App\DTO\Avatar\AvatarUploadData;
use Illuminate\Support\Facades\Log;
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

        Log::info('ui_save_flow_started', [
            'flow' => 'avatar',
            'boundary' => 'before_persistence',
            'user_id' => auth()->id(),
        ]);

        try {
            $this->validate([
                'avatar' => 'image|max:2048',
            ]);

            $user = app(PersistClientAvatarAction::class)->execute(
                auth()->user(),
                new AvatarUploadData($this->avatar),
            );

            Log::info('ui_save_flow_succeeded', [
                'flow' => 'avatar',
                'boundary' => 'after_persistence',
                'user_id' => auth()->id(),
            ]);

            $this->dispatch('avatar-saved', avatarUrl: $user->avatar_url);
            $this->dispatch('sheet:close', name: 'editAvatar');

            $this->reset('avatar');
        } catch (\Throwable $e) {
            Log::error('ui_save_flow_failed', [
                'flow' => 'avatar',
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
        return view('livewire.client.avatar-form');
    }
}
