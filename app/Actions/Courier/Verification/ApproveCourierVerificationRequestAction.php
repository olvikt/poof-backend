<?php

declare(strict_types=1);

namespace App\Actions\Courier\Verification;

use App\Models\CourierVerificationRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveCourierVerificationRequestAction
{
    public function execute(CourierVerificationRequest $request, User $reviewer): CourierVerificationRequest
    {
        if (! $reviewer->isAdmin()) {
            throw ValidationException::withMessages([
                'reviewer' => 'Лише адміністратор може підтвердити верифікацію.',
            ]);
        }

        return DB::transaction(function () use ($request, $reviewer): CourierVerificationRequest {
            $locked = CourierVerificationRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPendingReview()) {
                return $locked;
            }

            $locked->update([
                'status' => CourierVerificationRequest::STATUS_VERIFIED,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->id,
                'rejection_reason' => null,
            ]);

            $courier = $locked->courier()->lockForUpdate()->first();

            if ($courier) {
                $courier->forceFill(['is_verified' => true])->save();
                $courier->courierProfile()?->update(['is_verified' => true]);
            }

            return $locked->fresh();
        });
    }
}
