<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'ai_service' => [
        'url' => env('RIDE_AI_BASE_URL', 'https://ml-service-j72g.onrender.com'),
        'key' => env('RIDE_AI_API_KEY'),
        'timeout' => env('RIDE_AI_TIMEOUT', 10),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'sms_from' => env('TWILIO_SMS_FROM'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
        'default_country_code' => env('TWILIO_DEFAULT_COUNTRY_CODE', '+250'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe Payment Gateway
    |--------------------------------------------------------------------------
    | STRIPE_API_KEY         — secret key for server-side API calls
    | STRIPE_WEBHOOK_SECRET  — signing secret (whsec_...) from Stripe Dashboard
    */
    'stripe' => [
        'api_key' => env('STRIPE_API_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | MTN Mobile Money (MoMo)
    |--------------------------------------------------------------------------
    | MTN_CALLBACK_API_KEY — shared key sent in X-Callback-Api-Key header
    | MTN_COLLECTION_PRIMARY_KEY — subscription key from MoMo developer portal
    | MTN_BASE_URL  — sandbox: https://sandbox.momodeveloper.mtn.com
    */
    'mtn' => [
        'callback_api_key' => env('MTN_CALLBACK_API_KEY'),
        'api_user' => env('MTN_API_USER'),
        'api_key' => env('MTN_API_KEY'),
        'collection_primary_key' => env('MTN_COLLECTION_PRIMARY_KEY'),
        'base_url' => env('MTN_BASE_URL', 'https://sandbox.momodeveloper.mtn.com'),
        'currency' => env('MTN_CURRENCY', 'RWF'),
    ],

    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY', env('LARAMAP_GOOGLE_API_KEY')),
        'timeout' => env('GOOGLE_MAPS_API_TIMEOUT', 10), // seconds
        'retry_attempts' => env('GOOGLE_MAPS_RETRY_ATTEMPTS', 2), // retry count for failed requests
        'region' => env('GOOGLE_MAPS_REGION', 'rw'), // Rwanda default region
        'debug' => env('APP_DEBUG', false), // log all requests when debugging
    ],

    'ride_ai' => [
        'base_url' => env('RIDE_AI_BASE_URL', 'https://ml-service-j72g.onrender.com'),
        'api_key' => env('RIDE_AI_API_KEY'),
        'timeout' => env('RIDE_AI_TIMEOUT', 5),
    ],

    'ml_service' => [
        'url' => env('ML_SERVICE_URL', env('RIDE_AI_BASE_URL', 'https://ml-service-j72g.onrender.com')),
        'api_key' => env('ML_SERVICE_API_KEY', env('RIDE_AI_API_KEY')),
        'timeout' => env('ML_SERVICE_TIMEOUT', 10),
        'ranker_timeout' => env('ML_RANKER_TIMEOUT', 0.7),
        'ranking_enabled' => env('ML_RANKING_ENABLED', true),
    ],

    'tflite' => [
        'endpoint' => env('TFLITE_ENDPOINT', 'http://localhost:8001'),
        'timeout' => env('TFLITE_TIMEOUT', 15),
    ],

    'supabase' => [
        'url' => env('SUPABASE_URL'),
        'service_key' => env('SUPABASE_SERVICE_ROLE_KEY'),
    ],

    'ocr_space' => [
        'endpoint' => env('OCR_SPACE_ENDPOINT', 'https://api.ocr.space/parse/image'),
        'api_key' => env('OCR_SPACE_API_KEY', 'helloworld'),
        'timeout' => env('OCR_SPACE_TIMEOUT', 60),
    ],

    'push' => [
        // FCM legacy server key (or use your own gateway if preferred).
        'fcm_server_key' => env('FCM_SERVER_KEY'),

        // APNs token-based auth (.p8 key content can be stored inline with escaped newlines).
        'apns_key_id' => env('APNS_KEY_ID'),
        'apns_team_id' => env('APNS_TEAM_ID'),
        'apns_bundle_id' => env('APNS_BUNDLE_ID'),
        'apns_private_key' => env('APNS_PRIVATE_KEY'),
        'apns_use_sandbox' => env('APNS_USE_SANDBOX', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google OAuth
    |--------------------------------------------------------------------------
    | Configuration for Google OAuth 2.0 authentication
    */
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URL'),
    ],

];
