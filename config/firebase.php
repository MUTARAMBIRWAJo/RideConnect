<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Firebase Firestore integration with RideConnect backend
    |
    */

    'enabled' => env('FIREBASE_ENABLED', true),

    'bootstrap_enabled' => env('FIREBASE_BOOTSTRAP_ENABLED', false),

    'project_id' => env('FIREBASE_PROJECT_ID'),

    'credentials' => env('FIREBASE_CREDENTIALS_PATH', storage_path('firebase/credentials.json')),

    'database_url' => env('FIREBASE_DATABASE_URL'),

    'firestore_database' => env('FIREBASE_FIRESTORE_DATABASE', '(default)'),

    'api_key' => env('FIREBASE_API_KEY'),

    'auth_domain' => env('FIREBASE_AUTH_DOMAIN'),

    'app_id' => env('FIREBASE_APP_ID'),

    'messaging_sender_id' => env('FIREBASE_MESSAGING_SENDER_ID'),

    'storage_bucket' => env('FIREBASE_STORAGE_BUCKET'),

    /*
    |--------------------------------------------------------------------------
    | Sync Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Supabase → Firebase sync
    |
    */

    'sync' => [
        // Enable/disable automatic sync on model events
        'enabled' => env('FIREBASE_SYNC_ENABLED', true),

        // Retry failed syncs
        'retry_failed' => env('FIREBASE_SYNC_RETRY_FAILED', true),

        // Maximum retry attempts
        'max_retries' => env('FIREBASE_SYNC_MAX_RETRIES', 3),

        // Delay between retries (seconds)
        'retry_delay' => env('FIREBASE_SYNC_RETRY_DELAY', 5),

        // Queue name for sync jobs
        'queue' => env('FIREBASE_SYNC_QUEUE', 'default'),

        // Timeout for sync operations (seconds)
        'timeout' => env('FIREBASE_SYNC_TIMEOUT', 30),

        // Batch write operations
        'batch_write' => env('FIREBASE_SYNC_BATCH_WRITE', true),

        // Batch write size (max documents per batch)
        'batch_size' => env('FIREBASE_SYNC_BATCH_SIZE', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Collections Configuration
    |--------------------------------------------------------------------------
    |
    | Map Supabase tables to Firestore collections
    |
    */

    'collections' => [
        'users' => [
            'enabled' => true,
            'ttl_days' => 0, // 0 = no TTL
        ],
        'drivers' => [
            'enabled' => true,
            'ttl_days' => 0,
        ],
        'passengers' => [
            'enabled' => true,
            'ttl_days' => 0,
        ],
        'active_trips' => [
            'enabled' => true,
            'ttl_days' => 30, // Archive after 30 days
        ],
        'trip_requests' => [
            'enabled' => true,
            'ttl_days' => 1, // Auto-delete after 1 day
        ],
        'notifications' => [
            'enabled' => true,
            'ttl_days' => 30,
        ],
        'fcm_tokens' => [
            'enabled' => true,
            'ttl_days' => 90,
        ],
        'driver_ratings' => [
            'enabled' => true,
            'ttl_days' => 0,
        ],
        'passenger_ratings' => [
            'enabled' => true,
            'ttl_days' => 0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Control Firebase operation logging
    |
    */

    'logging' => [
        'enabled' => env('FIREBASE_LOGGING_ENABLED', true),

        'channel' => env('FIREBASE_LOGGING_CHANNEL', 'stack'),

        'log_level' => env('FIREBASE_LOGGING_LEVEL', 'debug'),

        // Log successful syncs (verbose)
        'log_success' => env('FIREBASE_LOG_SUCCESS', false),

        // Log failed syncs (recommended)
        'log_errors' => env('FIREBASE_LOG_ERRORS', true),

        // Log sync timing
        'log_timing' => env('FIREBASE_LOG_TIMING', false),
    ],
];
