<?php

declare(strict_types=1);

return [
    'default_provider' => env('PAYMENTS_PROVIDER', 'wayforpay'),

    'dev_fallback_enabled' => (bool) env('PAYMENTS_DEV_FALLBACK_ENABLED', false),

    'wayforpay' => [
        'enabled' => (bool) env('WAYFORPAY_ENABLED', true),
        'merchant_account' => env('WAYFORPAY_MERCHANT_ACCOUNT', ''),
        'merchant_secret' => env('WAYFORPAY_MERCHANT_SECRET', ''),
        'merchant_domain' => env('WAYFORPAY_MERCHANT_DOMAIN', 'app.poof.com.ua'),
        'service_url' => env('WAYFORPAY_SERVICE_URL', 'https://api.poof.com.ua/api/payments/wayforpay/callback'),
        'approved_url' => env('WAYFORPAY_APPROVED_URL', 'https://app.poof.com.ua/client/orders'),
        'declined_url' => env('WAYFORPAY_DECLINED_URL', 'https://app.poof.com.ua/client/orders'),
        'currency' => env('WAYFORPAY_CURRENCY', 'UAH'),
        'language' => env('WAYFORPAY_LANGUAGE', 'UA'),
        'pay_url' => env('WAYFORPAY_PAY_URL', 'https://secure.wayforpay.com/pay'),
    ],
];
