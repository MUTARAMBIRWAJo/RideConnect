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
        'url' => env('RIDE_AI_BASE_URL', 'https://rideconnect-ai.onrender.com'),
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
        'api_key'        => env('STRIPE_API_KEY'),
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
        'callback_api_key'          => env('MTN_CALLBACK_API_KEY'),
        'api_user'                   => env('MTN_API_USER'),
        'api_key'                    => env('MTN_API_KEY'),
        'collection_primary_key'     => env('MTN_COLLECTION_PRIMARY_KEY'),
        'base_url'                   => env('MTN_BASE_URL', 'https://sandbox.momodeveloper.mtn.com'),
        'currency'                   => env('MTN_CURRENCY', 'RWF'),
    ],

    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY', env('LARAMAP_GOOGLE_API_KEY')),
    ],

    'ride_ai' => [
        'base_url' => env('RIDE_AI_BASE_URL', 'http://ai-service:8001'),
        'api_key' => env('RIDE_AI_API_KEY'),
        'timeout' => env('RIDE_AI_TIMEOUT', 5),
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

];
