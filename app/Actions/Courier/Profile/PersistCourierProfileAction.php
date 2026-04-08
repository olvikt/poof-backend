<?php

declare(strict_types=1);

namespace App\Actions\Courier\Profile;

use App\DTO\Courier\Profile\CourierProfileUpdateData;
use App\Models\User;

class PersistCourierProfileAction
{
    public function execute(User $courier, CourierProfileUpdateData $payload): User
    {
        $courier->update($payload->toUserAttributes());

        $verificationStatus = $this->resolveVerificationStatus($courier->fresh());

        if ($verificationStatus !== $courier->courier_verification_status) {
            $courier->update(['courier_verification_status' => $verificationStatus]);
        }

        return $courier->fresh();
    }

    private function resolveVerificationStatus(User $courier): string
    {
        $hasBasics = filled($courier->name)
            && filled($courier->phone)
            && filled($courier->email)
            && filled($courier->residence_address);

        if (! $hasBasics) {
            return 'profile_incomplete';
        }

        if ($courier->courier_verification_status === 'verified') {
            return 'verified';
        }

        if ($courier->courier_verification_status === 'verification_pending') {
            return 'verification_pending';
        }

        return 'basic_profile_complete';
    }
}
