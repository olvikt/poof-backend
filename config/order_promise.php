<?php

return [
    'asap_validity_hours' => (int) env('ORDER_PROMISE_ASAP_VALIDITY_HOURS', 4),
    'preferred_window_grace_hours' => (int) env('ORDER_PROMISE_PREFERRED_WINDOW_GRACE_HOURS', 2),
    'allow_late_extra_hours' => (int) env('ORDER_PROMISE_ALLOW_LATE_EXTRA_HOURS', 6),
    'default_wait_preference' => env('ORDER_PROMISE_DEFAULT_WAIT_PREFERENCE', 'auto_cancel_if_not_found'),
    'auto_expire_enabled' => (bool) env('ORDER_PROMISE_AUTO_EXPIRE_ENABLED', true),
    'courier_urgency_warning_minutes' => (int) env('ORDER_PROMISE_COURIER_URGENCY_WARNING_MINUTES', 30),
    'policy_version' => env('ORDER_PROMISE_POLICY_VERSION', 'v1'),
];
