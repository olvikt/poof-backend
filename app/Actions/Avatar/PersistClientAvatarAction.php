<?php

namespace App\Actions\Avatar;

use App\DTO\Avatar\AvatarUploadData;
use App\Models\User;

class PersistClientAvatarAction
{
    public function execute(User $user, AvatarUploadData $avatarUpload): User
    {
        $user->update([
            'avatar' => $avatarUpload->storePath(),
        ]);

        return $user->fresh();
    }
}
