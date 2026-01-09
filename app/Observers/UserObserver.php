<?php

// app/Observers/UserObserver.php

namespace App\Observers;

use App\Models\User;
use App\Models\ClientProfile;

class UserObserver
{
    public function created(User $user): void
    {
        if ($user->role === 'client') {
            ClientProfile::firstOrCreate(
                ['user_id' => $user->id],
                ['name' => $user->name ?? '']
            );
        }
    }
}
