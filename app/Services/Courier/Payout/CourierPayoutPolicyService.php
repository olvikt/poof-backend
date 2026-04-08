<?php

declare(strict_types=1);

namespace App\Services\Courier\Payout;

use App\Models\CourierWithdrawalRequest;
use App\Models\User;

class CourierPayoutPolicyService
{
    public function summaryFor(User $courier, int $availableToWithdraw): array
    {
        $minimum = max(0, (int) config('courier_payout.minimum_withdrawal_amount', 500));

        $hasPendingRequest = CourierWithdrawalRequest::query()
            ->where('courier_id', $courier->id)
            ->whereIn('status', [
                CourierWithdrawalRequest::STATUS_REQUESTED,
                CourierWithdrawalRequest::STATUS_APPROVED,
            ])
            ->exists();

        if ($hasPendingRequest) {
            return [
                'can_request_withdrawal' => false,
                'min_withdrawal_amount' => $minimum,
                'withdrawal_block_reason' => 'pending_request_exists',
            ];
        }

        if ($availableToWithdraw < $minimum) {
            return [
                'can_request_withdrawal' => false,
                'min_withdrawal_amount' => $minimum,
                'withdrawal_block_reason' => 'below_minimum',
            ];
        }

        return [
            'can_request_withdrawal' => true,
            'min_withdrawal_amount' => $minimum,
            'withdrawal_block_reason' => null,
        ];
    }
}
