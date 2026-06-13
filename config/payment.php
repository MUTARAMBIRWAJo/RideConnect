<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Webhook Configuration
    |--------------------------------------------------------------------------
    */
    'webhook' => [
        'max_retries' => env('PAYMENT_WEBHOOK_MAX_RETRIES', 3),
        'retry_delay_minutes' => env('PAYMENT_WEBHOOK_RETRY_DELAY_MINUTES', 5),
        'timeout_seconds' => env('PAYMENT_WEBHOOK_TIMEOUT_SECONDS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Reconciliation Configuration
    |--------------------------------------------------------------------------
    */
    'reconciliation' => [
        'enabled' => env('PAYMENT_RECONCILIATION_ENABLED', true),
        'schedule' => env('PAYMENT_RECONCILIATION_SCHEDULE', '0 2 * * *'), // Daily at 2 AM
        'lookback_days' => env('PAYMENT_RECONCILIATION_LOOKBACK_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Payment Providers
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'stripe' => [
            'enabled' => env('STRIPE_ENABLED', true),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'api_key' => env('STRIPE_SECRET_KEY'),
        ],
        'mtn_momo' => [
            'enabled' => env('MTN_MOMO_ENABLED', true),
            'callback_api_key' => env('MTN_CALLBACK_API_KEY'),
            'api_key' => env('MTN_API_KEY'),
            'subscription_key' => env('MTN_SUBSCRIPTION_KEY'),
        ],
        'cash' => [
            'enabled' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fee Configuration
    |--------------------------------------------------------------------------
    */
    'fees' => [
        'platform_percentage' => env('PLATFORM_FEE_PERCENTAGE', 8.0), // 8%
        'driver_percentage' => env('DRIVER_FEE_PERCENTAGE', 92.0), // 92%
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency Configuration
    |--------------------------------------------------------------------------
    */
    'currency' => [
        'default' => env('PAYMENT_CURRENCY_DEFAULT', 'RWF'),
        'allowed' => explode(',', env('PAYMENT_CURRENCY_ALLOWED', 'RWF,USD,EUR')),
    ],
];
