<?php

declare(strict_types=1);

namespace App\Services\Courier\Wallet;

use App\Models\CourierPayoutRequisite;
use App\Models\CourierWithdrawalRequest;
use App\Models\User;
use App\Services\Courier\Earnings\CourierBalanceSummaryService;
use App\Services\Courier\Earnings\CourierCompletedOrdersDailyStatsQuery;
use App\Services\Courier\Payout\CourierPayoutPolicyService;

class CourierWalletReadModelService
{
    public function __construct(
        private readonly CourierBalanceSummaryService $balanceSummaryService,
        private readonly CourierPayoutPolicyService $payoutPolicyService,
        private readonly CourierCompletedOrdersDailyStatsQuery $completedOrdersDailyStatsQuery,
    ) {
    }

    public function forCourier(User $courier): array
    {
        $balance = $this->balanceSummaryService->forCourier($courier);
        $currentBalance = (int) ($balance['courier_net_balance'] ?? 0);

        $pendingAmount = (int) CourierWithdrawalRequest::query()
            ->where('courier_id', $courier->id)
            ->whereIn('status', [
                CourierWithdrawalRequest::STATUS_REQUESTED,
                CourierWithdrawalRequest::STATUS_APPROVED,
            ])
            ->sum('amount');

        $availableToWithdraw = max(0, $currentBalance - $pendingAmount);
        $payoutPolicy = $this->payoutPolicyService->summaryFor($courier, $availableToWithdraw);

        $withdrawals = CourierWithdrawalRequest::query()
            ->where('courier_id', $courier->id)
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(function (CourierWithdrawalRequest $request): array {
                return [
                    'id' => $request->id,
                    'amount' => (int) $request->amount,
                    'amount_formatted' => $this->formatUah((int) $request->amount),
                    'status' => (string) $request->status,
                    'status_label' => $this->withdrawalStatusLabel((string) $request->status),
                    'requested_at' => optional($request->created_at)?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        $requisites = CourierPayoutRequisite::query()
            ->where('courier_id', $courier->id)
            ->first();

        return [
            'balance_summary' => [
                'current_balance' => $currentBalance,
                'current_balance_formatted' => $this->formatUah($currentBalance),
                'available_to_withdraw' => $availableToWithdraw,
                'available_to_withdraw_formatted' => $this->formatUah($availableToWithdraw),
                'held_amount' => $pendingAmount,
                'held_amount_formatted' => $this->formatUah($pendingAmount),
                'pending_amount' => $pendingAmount,
                'pending_amount_formatted' => $this->formatUah($pendingAmount),
                'minimum_withdrawal_amount' => (int) ($payoutPolicy['min_withdrawal_amount'] ?? 0),
                'minimum_withdrawal_amount_formatted' => $this->formatUah((int) ($payoutPolicy['min_withdrawal_amount'] ?? 0)),
                'can_request_withdrawal' => (bool) ($payoutPolicy['can_request_withdrawal'] ?? false),
                'withdrawal_block_reason' => $payoutPolicy['withdrawal_block_reason'] ?? null,
                'withdrawal_block_message' => $this->withdrawalBlockReasonLabel(
                    isset($payoutPolicy['withdrawal_block_reason']) ? (string) $payoutPolicy['withdrawal_block_reason'] : null
                ),
            ],
            'earnings_summary' => [
                'completed_orders_count' => (int) ($balance['completed_orders_count'] ?? 0),
                'gross_earnings_total' => (int) ($balance['gross_earnings_total'] ?? 0),
                'gross_earnings_total_formatted' => $this->formatUah((int) ($balance['gross_earnings_total'] ?? 0)),
                'platform_commission_total' => (int) ($balance['platform_commission_total'] ?? 0),
                'platform_commission_total_formatted' => $this->formatUah((int) ($balance['platform_commission_total'] ?? 0)),
                'courier_net_balance' => $currentBalance,
                'courier_net_balance_formatted' => $this->formatUah($currentBalance),
            ],
            'recent_earnings_days' => $this->completedOrdersDailyStatsQuery->forCourier($courier)->take(7)->values()->all(),
            'recent_withdrawal_requests' => $withdrawals,
            'payout_requisites' => [
                'has_requisites' => $requisites !== null,
                'card_holder_name' => (string) ($requisites?->card_holder_name ?? ''),
                'masked_card_number' => (string) ($requisites?->masked_card_number ?? ''),
                'bank_name' => (string) ($requisites?->bank_name ?? ''),
                'notes' => (string) ($requisites?->notes ?? ''),
            ],
        ];
    }

    private function withdrawalStatusLabel(string $status): string
    {
        return match ($status) {
            CourierWithdrawalRequest::STATUS_REQUESTED => 'Створено',
            CourierWithdrawalRequest::STATUS_APPROVED => 'Підтверджено',
            CourierWithdrawalRequest::STATUS_REJECTED => 'Відхилено',
            CourierWithdrawalRequest::STATUS_PAID => 'Виплачено',
            default => $status,
        };
    }

    private function withdrawalBlockReasonLabel(?string $reason): ?string
    {
        if ($reason === null || $reason === '') {
            return null;
        }

        return match ($reason) {
            'below_minimum' => 'Мінімальна сума для виводу ще не досягнута.',
            'insufficient_balance' => 'Недостатньо доступного балансу для виводу.',
            'pending_request_exists' => 'У вас уже є активний запит на вивід.',
            default => 'Наразі запит на вивід недоступний.',
        };
    }

    private function formatUah(int $amount): string
    {
        return number_format($amount, 2, ',', ' ').' ₴';
    }
}
