<?php

return [
    'radius_km' => 20,
    'search_radius_km' => 5,
    'offer_timeout_seconds' => 20,
    'max_couriers_notified' => 5,
    'courier_map_bootstrap_debug' => (bool) env('COURIER_MAP_BOOTSTRAP_DEBUG', false),
];
