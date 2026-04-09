<?php

declare(strict_types=1);

namespace App\Services\Courier\Profile;

use App\Models\User;
use App\Services\Courier\Earnings\CourierBalanceSummaryService;
use App\Services\Courier\Payout\CourierPayoutPolicyService;
use App\Services\Courier\Rating\CourierRatingSummaryService;
use App\Services\Courier\Verification\CourierVerificationSummaryService;
use App\Support\Courier\Profile\CourierProfileWidgetCacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
        $profileIdentity = $this->resolveCachedBlock(
            $courier,
            CourierProfileWidgetCacheKeys::PROFILE_IDENTITY,
            fn (): array => [
                'full_name' => (string) $courier->name,
            ],
        );

        $profileContact = $this->resolveCachedBlock(
            $courier,
            CourierProfileWidgetCacheKeys::PROFILE_CONTACT,
            fn (): array => [
                'phone' => (string) ($courier->phone ?? '—'),
                'email' => (string) $courier->email,
            ],
        );

        $profileAddress = $this->resolveCachedBlock(
            $courier,
            CourierProfileWidgetCacheKeys::PROFILE_ADDRESS,
            fn (): array => [
                'residence_address' => (string) ($courier->residence_address ?? '—'),
            ],
        );

        $profileMedia = $this->resolveCachedBlock(
            $courier,
            CourierProfileWidgetCacheKeys::PROFILE_MEDIA,
            fn (): array => [
                'avatar_url' => $courier->avatar_url,
            ],
        );

        $profileVerification = $this->resolveCachedBlock(
            $courier,
            CourierProfileWidgetCacheKeys::PROFILE_VERIFICATION,
            fn (): array => $this->verificationSummaryService->forCourier($courier),
        );

        $ratingSummary = $this->resolveCachedBlock(
            $courier,
            CourierProfileWidgetCacheKeys::RATING_SUMMARY,
            fn (): array => $this->ratingSummaryService->forCourier($courier),
        );

        $balanceSummary = $this->resolveCachedBlock(
            $courier,
            CourierProfileWidgetCacheKeys::BALANCE_SUMMARY,
            function () use ($courier): array {
                $balance = $this->balanceSummaryService->forCourier($courier);
                $availableToWithdraw = (int) ($balance['courier_net_balance'] ?? 0);
                $payoutPolicy = $this->payoutPolicyService->summaryFor($courier, $availableToWithdraw);

                return [
                    'earned_total' => (int) ($balance['gross_earnings_total'] ?? 0),
                    'available_to_withdraw' => $availableToWithdraw,
                    'held_amount' => 0,
                    'pending_hold_amount' => 0,
                    'can_request_withdrawal' => (bool) ($payoutPolicy['can_request_withdrawal'] ?? false),
                    'min_withdrawal_amount' => (int) ($payoutPolicy['min_withdrawal_amount'] ?? 0),
                    'withdrawal_block_reason' => $payoutPolicy['withdrawal_block_reason'] ?? null,
                    'balance_formatted' => $balance['balance_formatted'] ?? '0,00 ₴',
                ];
            },
        );

        return [
            'profile_identity' => $profileIdentity,
            'profile_contact' => $profileContact,
            'profile_address' => $profileAddress,
            'profile_media' => $profileMedia,
            'profile_verification' => $profileVerification,
            'rating_summary' => $ratingSummary,
            'balance_summary' => $balanceSummary,
        ];
    }

    private function resolveCachedBlock(User $courier, string $widget, callable $resolver): array
    {
        $ttlSeconds = max(0, (int) config('courier_profile_cache.ttl_seconds.'.$widget, 0));
        $enabled = (bool) config('courier_profile_cache.enabled', true);

        if (! $enabled || $ttlSeconds === 0) {
            return $resolver();
        }

        $cacheKey = CourierProfileWidgetCacheKeys::forWidget((int) $courier->id, $widget);

        try {
            $cached = Cache::get($cacheKey);
        } catch (\Throwable $exception) {
            Log::warning('courier_profile_cache_fallback', [
                'widget' => $widget,
                'courier_id' => $courier->id,
                'cache_key_group' => 'courier_profile_widgets',
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $resolver();
        }

        if (is_array($cached)) {
            Log::info('courier_profile_cache_hit', [
                'widget' => $widget,
                'courier_id' => $courier->id,
                'cache_key_group' => 'courier_profile_widgets',
            ]);

            return $cached;
        }

        Log::info('courier_profile_cache_miss', [
            'widget' => $widget,
            'courier_id' => $courier->id,
            'cache_key_group' => 'courier_profile_widgets',
        ]);

        $computed = $resolver();

        try {
            Cache::put($cacheKey, $computed, now()->addSeconds($ttlSeconds));
        } catch (\Throwable $exception) {
            Log::warning('courier_profile_cache_fallback', [
                'widget' => $widget,
                'courier_id' => $courier->id,
                'cache_key_group' => 'courier_profile_widgets',
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        return $computed;
    }
}
