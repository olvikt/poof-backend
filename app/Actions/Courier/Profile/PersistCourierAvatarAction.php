<?php

declare(strict_types=1);

namespace App\Actions\Courier\Profile;

use App\DTO\Avatar\AvatarUploadData;
use App\Models\User;
use App\Services\Courier\Profile\CourierProfileWidgetCacheInvalidator;

class PersistCourierAvatarAction
{
    public function __construct(
        private readonly CourierProfileWidgetCacheInvalidator $cacheInvalidator,
    ) {
    }

    public function execute(User $courier, AvatarUploadData $avatarUpload): User
    {
        $courier->update([
            'avatar' => $avatarUpload->storePath(),
        ]);

        $this->cacheInvalidator->invalidateProfileMedia($courier);

        return $courier->fresh();
    }
}
