<?php

return [
    'radius_km' => 20,
    'search_radius_km' => 5,
    'offer_timeout_seconds' => 20,
    'max_couriers_notified' => 5,
    'courier_map_bootstrap_debug' => (bool) env('COURIER_MAP_BOOTSTRAP_DEBUG', false),


    'fairness' => [
        'max_offer_attempts_per_courier' => (int) env('DISPATCH_MAX_OFFER_ATTEMPTS_PER_COURIER', 3),
        'reoffer_cooldown_minutes' => (int) env('DISPATCH_REOFFER_COOLDOWN_MINUTES', 5),
        'distance_weight' => (float) env('DISPATCH_DISTANCE_WEIGHT', 1.0),
        'workload_penalty_weight' => (float) env('DISPATCH_WORKLOAD_PENALTY_WEIGHT', 0.6),
        'recency_penalty_weight' => (float) env('DISPATCH_RECENCY_PENALTY_WEIGHT', 0.2),
        'starvation_step_seconds' => (int) env('DISPATCH_STARVATION_STEP_SECONDS', 120),
        'starvation_radius_step_km' => (float) env('DISPATCH_STARVATION_RADIUS_STEP_KM', 1.5),
        'starvation_max_extra_radius_km' => (float) env('DISPATCH_STARVATION_MAX_EXTRA_RADIUS_KM', 10.0),
    ],

    'trigger' => [
        'location_cooldown_ms' => (int) env('DISPATCH_TRIGGER_LOCATION_COOLDOWN_MS', 5000),
        'location_movement_threshold_meters' => (float) env('DISPATCH_TRIGGER_LOCATION_MOVEMENT_THRESHOLD_METERS', 50),
        'scheduler_cooldown_ms' => (int) env('DISPATCH_TRIGGER_SCHEDULER_COOLDOWN_MS', 3000),
        'order_cooldown_ms' => (int) env('DISPATCH_TRIGGER_ORDER_COOLDOWN_MS', 1200),
        'order_completed_cooldown_ms' => (int) env('DISPATCH_TRIGGER_ORDER_COMPLETED_COOLDOWN_MS', 1000),
    ],
];
