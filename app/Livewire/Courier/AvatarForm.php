<?php

declare(strict_types=1);

namespace App\Livewire\Courier;

use App\Actions\Courier\Profile\PersistCourierAvatarAction;
use App\DTO\Avatar\AvatarUploadData;
use App\Models\User;
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
            'avatar' => ['image', 'max:2048'],
        ]);

        $courier = auth()->user();

        if (! $courier instanceof User || ! $courier->isCourier()) {
            abort(403);
        }

        $freshCourier = app(PersistCourierAvatarAction::class)->execute(
            $courier,
            new AvatarUploadData($this->avatar),
        );

        $this->dispatch('avatar-saved', avatarUrl: $freshCourier->avatar_url);
        $this->dispatch('sheet:close', name: 'courierEditAvatar');

        $this->reset('avatar');
    }

    public function render()
    {
        return view('livewire.courier.avatar-form', [
            'courier' => auth()->user(),
        ]);
    }
}
