<?php

namespace App\Actions\Profile;

use App\DTO\Profile\ProfileFormData;
use App\Models\User;

class PersistClientProfileAction
{
    public function execute(User $user, ProfileFormData $profile): User
    {
        $user->update($profile->toArray());

        return $user->fresh();
    }
}
