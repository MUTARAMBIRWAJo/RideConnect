<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Realtime config for Flutter consumers
    |--------------------------------------------------------------------------
    |
    | This endpoint is the single source of truth for the Flutter app to
    | decide whether to use Firebase Firestore event delivery or fall back
    | to HTTP polling.
    |
    | Change these values when deploying a different realtime backend.
    |
    */

    'enabled' => env('REALTIME_ENABLED', false),

    /*
    | Provider hint for the Flutter SDK. When enabled is true, Flutter uses
    | this to select the correct socket implementation.
    | Supported: firebase, supabase, polling
    */
    'provider' => env('REALTIME_PROVIDER', 'firebase'),

    /*
    | Flutter connects to these when realtime IS enabled.
    | APP_URL is used as the base (e.g. https://rideconnect-emp0.onrender.com).
    */
    'host' => env('REALTIME_HOST', env('APP_URL', 'https://rideconnect-emp0.onrender.com')),
    'port' => (int) env('REALTIME_PORT', 443),
    'scheme' => env('REALTIME_SCHEME', 'https'),
    'ws_path' => env('REALTIME_WS_PATH', 'ws'),

    /*
    | Channel naming convention — must match Flutter's channel builder.
    */
    'channels' => [
        'trip'   => 'trip.{tripId}',
        'driver' => 'driver.{driverId}',
        'user'   => 'user.{userId}',
    ],
];
