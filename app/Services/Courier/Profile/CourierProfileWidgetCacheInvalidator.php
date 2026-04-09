<?php

declare(strict_types=1);

namespace App\Services\Courier\Profile;

use App\Models\User;
use App\Support\Courier\Profile\CourierProfileWidgetCacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CourierProfileWidgetCacheInvalidator
{
    public function invalidateProfileIdentity(User $courier): void
    {
        $this->forgetMany($courier, [
            CourierProfileWidgetCacheKeys::PROFILE_IDENTITY,
            CourierProfileWidgetCacheKeys::PROFILE_CONTACT,
            CourierProfileWidgetCacheKeys::PROFILE_ADDRESS,
        ]);
    }

    public function invalidateProfileMedia(User $courier): void
    {
        $this->forgetMany($courier, [
            CourierProfileWidgetCacheKeys::PROFILE_MEDIA,
        ]);
    }

    public function invalidateBalanceSummary(User $courier): void
    {
        $this->forgetMany($courier, [
            CourierProfileWidgetCacheKeys::BALANCE_SUMMARY,
        ]);
    }

    private function forgetMany(User $courier, array $widgets): void
    {
        foreach ($widgets as $widget) {
            $key = CourierProfileWidgetCacheKeys::forWidget((int) $courier->id, (string) $widget);

            try {
                Cache::forget($key);
            } catch (\Throwable $exception) {
                Log::warning('courier_profile_cache_invalidation_failed', [
                    'courier_id' => $courier->id,
                    'widget' => $widget,
                    'cache_key_group' => 'courier_profile_widgets',
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }
}
