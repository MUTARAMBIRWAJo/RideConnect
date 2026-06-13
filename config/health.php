<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Production Health Monitoring
    |--------------------------------------------------------------------------
    */

    'timeouts' => [
        'database_ms' => (int) env('HEALTH_DB_TIMEOUT_MS', 3000),
        'firebase_ms' => (int) env('HEALTH_FIREBASE_TIMEOUT_MS', 3000),
        'ml_service_ms' => (int) env('HEALTH_ML_TIMEOUT_MS', 5000),
        'queue_ms' => (int) env('HEALTH_QUEUE_TIMEOUT_MS', 2000),
        'storage_ms' => (int) env('HEALTH_STORAGE_TIMEOUT_MS', 1000),
    ],

    'ml_service' => [
        'url' => env('ML_SERVICE_URL', env('TFLITE_ENDPOINT', env('RIDE_AI_BASE_URL', 'https://ml-service-j72g.onrender.com'))),
        'health_path' => env('HEALTH_ML_HEALTH_PATH', '/health'),
        'prediction_probe_path' => env('HEALTH_ML_PREDICTION_PROBE_PATH', '/rank-drivers'),
    ],

    'storage_paths' => [
        'framework/cache',
        'framework/sessions',
        'framework/views',
        'logs',
        'app',
    ],

    'ready_requires' => [
        'database',
        'queue',
    ],

    'optional_ready_checks' => [
        'firebase',
        'ml_service',
    ],

];
