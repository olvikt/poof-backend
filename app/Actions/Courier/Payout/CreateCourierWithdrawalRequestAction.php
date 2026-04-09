<?php

declare(strict_types=1);

namespace App\Actions\Courier\Payout;

use App\Models\CourierWithdrawalRequest;
use App\Models\User;
use App\Services\Courier\Earnings\CourierBalanceSummaryService;
use App\Services\Courier\Payout\CourierPayoutPolicyService;
use App\Services\Courier\Profile\CourierProfileWidgetCacheInvalidator;
use Illuminate\Validation\ValidationException;

class CreateCourierWithdrawalRequestAction
{
    public function __construct(
        private readonly CourierBalanceSummaryService $balanceSummaryService,
        private readonly CourierPayoutPolicyService $policyService,
        private readonly CourierProfileWidgetCacheInvalidator $cacheInvalidator,
    ) {
    }

    public function execute(User $courier, int $amount, ?string $notes = null): CourierWithdrawalRequest
    {
        $summary = $this->balanceSummaryService->forCourier($courier);
        $availableToWithdraw = (int) ($summary['courier_net_balance'] ?? 0);

        $policy = $this->policyService->summaryFor($courier, $availableToWithdraw);

        if (! ($policy['can_request_withdrawal'] ?? false)) {
            throw ValidationException::withMessages([
                'amount' => 'Запит на вивід зараз недоступний.',
            ]);
        }

        if ($amount < (int) $policy['min_withdrawal_amount']) {
            throw ValidationException::withMessages([
                'amount' => 'Сума нижча за мінімальний поріг виводу.',
            ]);
        }

        if ($amount > $availableToWithdraw) {
            throw ValidationException::withMessages([
                'amount' => 'Недостатньо доступного балансу для виводу.',
            ]);
        }

        $request = CourierWithdrawalRequest::query()->create([
            'courier_id' => $courier->id,
            'amount' => $amount,
            'status' => CourierWithdrawalRequest::STATUS_REQUESTED,
            'notes' => $notes,
        ]);

        $this->cacheInvalidator->invalidateBalanceSummary($courier);

        return $request;
    }
}
