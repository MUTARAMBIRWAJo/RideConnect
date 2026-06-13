<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Driver Location Configuration
    |--------------------------------------------------------------------------
    */
    
    /*
    |--------------------------------------------------------------------------
    | Online Status Thresholds
    |--------------------------------------------------------------------------
    */
    'online_timeout_minutes' => env('DRIVER_ONLINE_TIMEOUT_MINUTES', 5),
    'stale_location_threshold_minutes' => env('DRIVER_STALE_LOCATION_THRESHOLD_MINUTES', 15),
    
    /*
    |--------------------------------------------------------------------------
    | Location Validation
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'max_speed_kmh' => env('DRIVER_MAX_SPEED_KMH', 200),
        'max_accuracy_meters' => env('DRIVER_MAX_ACCURACY_METERS', 1000),
        'max_distance_jump_km' => env('DRIVER_MAX_DISTANCE_JUMP_KM', 10),
        'require_within_rwanda' => env('DRIVER_REQUIRE_WITHIN_RWANDA', false),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => env('DRIVER_LOCATION_CACHE_ENABLED', true),
        'ttl_minutes' => env('DRIVER_LOCATION_CACHE_TTL_MINUTES', 10),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Firebase Sync
    |--------------------------------------------------------------------------
    */
    'firebase_sync' => [
        'enabled' => env('DRIVER_FIREBASE_SYNC_ENABLED', true),
        'async' => env('DRIVER_FIREBASE_SYNC_ASYNC', true),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Cleanup
    |--------------------------------------------------------------------------
    */
    'cleanup' => [
        'enabled' => env('DRIVER_LOCATION_CLEANUP_ENABLED', true),
        'schedule' => env('DRIVER_LOCATION_CLEANUP_SCHEDULE', '*/5 * * * *'), // Every 5 minutes
        'retention_days' => env('DRIVER_LOCATION_RETENTION_DAYS', 30),
    ],
];
