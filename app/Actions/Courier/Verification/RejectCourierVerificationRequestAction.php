<?php

declare(strict_types=1);

namespace App\Actions\Courier\Verification;

use App\Models\CourierVerificationRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejectCourierVerificationRequestAction
{
    public function execute(CourierVerificationRequest $request, User $reviewer, string $reason): CourierVerificationRequest
    {
        if (! $reviewer->isAdmin()) {
            throw ValidationException::withMessages([
                'reviewer' => 'Лише адміністратор може відхилити верифікацію.',
            ]);
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'rejection_reason' => 'Потрібно вказати причину відхилення.',
            ]);
        }

        return DB::transaction(function () use ($request, $reviewer, $reason): CourierVerificationRequest {
            $locked = CourierVerificationRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPendingReview()) {
                return $locked;
            }

            $locked->update([
                'status' => CourierVerificationRequest::STATUS_REJECTED,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->id,
                'rejection_reason' => $reason,
            ]);

            $courier = $locked->courier()->lockForUpdate()->first();

            if ($courier) {
                $courier->forceFill(['is_verified' => false])->save();
                $courier->courierProfile()?->update(['is_verified' => false]);
            }

            return $locked->fresh();
        });
    }
}
