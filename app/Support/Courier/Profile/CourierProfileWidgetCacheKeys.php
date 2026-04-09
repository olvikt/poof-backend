<?php

declare(strict_types=1);

namespace App\Support\Courier\Profile;

class CourierProfileWidgetCacheKeys
{
    public const PROFILE_IDENTITY = 'profile_identity';

    public const PROFILE_CONTACT = 'profile_contact';

    public const PROFILE_ADDRESS = 'profile_address';

    public const PROFILE_MEDIA = 'profile_media';

    public const RATING_SUMMARY = 'rating_summary';

    public const BALANCE_SUMMARY = 'balance_summary';

    public static function forWidget(int $courierId, string $widget): string
    {
        return sprintf('courier:%d:profile:%s', $courierId, $widget);
    }
}
