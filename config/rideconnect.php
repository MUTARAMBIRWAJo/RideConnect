<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Supabase PostgreSQL Source of Truth Rules
    |--------------------------------------------------------------------------
    |
    | Define restricted domains and fields that are owned strictly by the
    | PostgreSQL database. Attempts to write these fields directly to Firebase
    | from a non-Postgres-sync context will throw an exception.
    |
    */
    'source_of_truth' => [
        'trips' => ['status', 'driver_id', 'passenger_id', 'estimated_fare', 'actual_fare'],
        'payments' => ['status', 'amount', 'currency', 'transaction_id'],
        'users' => ['email', 'name', 'phone', 'role'],
        'drivers' => ['user_id', 'status', 'vehicle_type', 'license_plate'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Driver Telemetry Options
    |--------------------------------------------------------------------------
    |
    | Controls telemetry rate limits. Updates within this threshold window
    | will be throttled to reduce network overhead.
    |
    */
    'firebase' => [
        'location_update_interval_seconds' => (int) env('FIREBASE_LOCATION_UPDATE_INTERVAL_SECONDS', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | ML Service API Integration Options
    |--------------------------------------------------------------------------
    */
    'ml' => [
        'service_url' => env('ML_SERVICE_URL', 'https://ml-service-j72g.onrender.com'),
        'api_key' => env('ML_SERVICE_API_KEY', ''),
        'timeout' => (int) env('ML_SERVICE_TIMEOUT', 10),
    ],
];
