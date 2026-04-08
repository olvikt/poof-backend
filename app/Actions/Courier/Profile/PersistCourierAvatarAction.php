<?php

declare(strict_types=1);

namespace App\Actions\Courier\Profile;

use App\DTO\Avatar\AvatarUploadData;
use App\Models\User;

class PersistCourierAvatarAction
{
    public function execute(User $courier, AvatarUploadData $avatarUpload): User
    {
        $courier->update([
            'avatar' => $avatarUpload->storePath(),
        ]);

        return $courier->fresh();
    }
}
