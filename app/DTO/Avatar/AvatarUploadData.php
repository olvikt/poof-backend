<?php

namespace App\DTO\Avatar;

use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AvatarUploadData
{
    public function __construct(
        public readonly TemporaryUploadedFile $avatar,
    ) {
    }

    public function storePath(): string
    {
        return $this->avatar->store('avatars', 'public');
    }
}
