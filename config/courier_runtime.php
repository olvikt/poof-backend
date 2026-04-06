<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Courier location heartbeat contract
    |--------------------------------------------------------------------------
    |
    | max_accuracy_meters must match frontend courier tracker acceptance guard.
    | Keeping this value unified prevents healthy heartbeats from being dropped
    | by backend validation and accidental stale/offline transitions.
    |
    */
    'heartbeat' => [
        'max_accuracy_meters' => (float) env('COURIER_HEARTBEAT_MAX_ACCURACY_METERS', 120),
        'diagnostic_logging' => (bool) env('COURIER_HEARTBEAT_DIAGNOSTIC_LOGGING', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Courier stale / freshness thresholds
    |--------------------------------------------------------------------------
    */
    'freshness' => [
        'map_active_location_seconds' => (int) env('COURIER_MAP_ACTIVE_LOCATION_SECONDS', 60),
        'dispatch_candidate_location_seconds' => (int) env('COURIER_DISPATCH_LOCATION_SECONDS', 60),
        'offline_stale_seconds' => (int) env('COURIER_OFFLINE_STALE_SECONDS', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Incident diagnostics (P0 2026-04-06)
    |--------------------------------------------------------------------------
    */
    'incident_logging' => [
        'enabled' => (bool) env('COURIER_INCIDENT_RUNTIME_LOGGING', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Operational diagnostics hardening (P1 2026-04-06)
    |--------------------------------------------------------------------------
    */
    'pending_offer_sweeper' => [
        'limit' => (int) env('COURIER_PENDING_OFFER_SWEEP_LIMIT', 200),
    ],

    'searching_diagnostics' => [
        'future_grace_minutes' => (int) env('COURIER_SEARCHING_FUTURE_GRACE_MINUTES', 10),
        'max_searching_age_minutes' => (int) env('COURIER_SEARCHING_MAX_AGE_MINUTES', 20),
        'candidate_scan_limit' => (int) env('COURIER_SEARCHING_CANDIDATE_SCAN_LIMIT', 160),
    ],
];
