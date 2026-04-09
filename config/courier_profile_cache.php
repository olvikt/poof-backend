<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('COURIER_PROFILE_CACHE_ENABLED', true),

    'ttl_seconds' => [
        'profile_identity' => (int) env('COURIER_PROFILE_CACHE_PROFILE_IDENTITY_TTL_SECONDS', 300),
        'profile_contact' => (int) env('COURIER_PROFILE_CACHE_PROFILE_CONTACT_TTL_SECONDS', 300),
        'profile_address' => (int) env('COURIER_PROFILE_CACHE_PROFILE_ADDRESS_TTL_SECONDS', 300),
        'profile_media' => (int) env('COURIER_PROFILE_CACHE_PROFILE_MEDIA_TTL_SECONDS', 300),
        'rating_summary' => (int) env('COURIER_PROFILE_CACHE_RATING_SUMMARY_TTL_SECONDS', 120),
        'balance_summary' => (int) env('COURIER_PROFILE_CACHE_BALANCE_SUMMARY_TTL_SECONDS', 60),
    ],
];
