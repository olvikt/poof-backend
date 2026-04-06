<?php

return [
    'radius_km' => 20,
    'search_radius_km' => 5,
    'offer_timeout_seconds' => 20,
    'max_couriers_notified' => 5,
    'courier_map_bootstrap_debug' => (bool) env('COURIER_MAP_BOOTSTRAP_DEBUG', false),

    'trigger' => [
        'location_cooldown_ms' => (int) env('DISPATCH_TRIGGER_LOCATION_COOLDOWN_MS', 5000),
        'location_movement_threshold_meters' => (float) env('DISPATCH_TRIGGER_LOCATION_MOVEMENT_THRESHOLD_METERS', 50),
        'scheduler_cooldown_ms' => (int) env('DISPATCH_TRIGGER_SCHEDULER_COOLDOWN_MS', 3000),
        'order_cooldown_ms' => (int) env('DISPATCH_TRIGGER_ORDER_COOLDOWN_MS', 1200),
        'order_completed_cooldown_ms' => (int) env('DISPATCH_TRIGGER_ORDER_COMPLETED_COOLDOWN_MS', 1000),
    ],
];
