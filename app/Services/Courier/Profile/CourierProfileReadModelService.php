<?php

declare(strict_types=1);

namespace App\Services\Courier\Profile;

use App\Models\User;
use App\Services\Courier\Earnings\CourierBalanceSummaryService;
use App\Services\Courier\Payout\CourierPayoutPolicyService;
use App\Services\Courier\Rating\CourierRatingSummaryService;
use App\Services\Courier\Verification\CourierVerificationSummaryService;

class CourierProfileReadModelService
{
    public function __construct(
        private readonly CourierRatingSummaryService $ratingSummaryService,
        private readonly CourierBalanceSummaryService $balanceSummaryService,
        private readonly CourierPayoutPolicyService $payoutPolicyService,
        private readonly CourierVerificationSummaryService $verificationSummaryService,
    ) {
    }

    public function forCourier(User $courier): array
    {
        $balance = $this->balanceSummaryService->forCourier($courier);
        $availableToWithdraw = (int) ($balance['courier_net_balance'] ?? 0);
        $payoutPolicy = $this->payoutPolicyService->summaryFor($courier, $availableToWithdraw);

        return [
            'profile_identity' => [
                'full_name' => (string) $courier->name,
            ],
            'profile_contact' => [
                'phone' => (string) ($courier->phone ?? '—'),
                'email' => (string) $courier->email,
            ],
            'profile_address' => [
                'residence_address' => (string) ($courier->residence_address ?? '—'),
            ],
            'profile_media' => [
                'avatar_url' => $courier->avatar_url,
            ],
            'profile_verification' => $this->verificationSummaryService->forCourier($courier),
            'rating_summary' => $this->ratingSummaryService->forCourier($courier),
            'balance_summary' => [
                'earned_total' => (int) ($balance['gross_earnings_total'] ?? 0),
                'available_to_withdraw' => $availableToWithdraw,
                'held_amount' => 0,
                'pending_hold_amount' => 0,
                'can_request_withdrawal' => (bool) ($payoutPolicy['can_request_withdrawal'] ?? false),
                'min_withdrawal_amount' => (int) ($payoutPolicy['min_withdrawal_amount'] ?? 0),
                'withdrawal_block_reason' => $payoutPolicy['withdrawal_block_reason'] ?? null,
                'balance_formatted' => $balance['balance_formatted'] ?? '0,00 ₴',
            ],
        ];
    }
}
