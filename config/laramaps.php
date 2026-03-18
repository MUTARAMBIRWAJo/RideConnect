<?php

return [
    'api_key' => env('LARAMAP_GOOGLE_API_KEY', env('GOOGLE_MAPS_API_KEY')),

    'map_options' => [
        'center' => [
            'lat' => -1.9441,
            'lng' => 30.0619,
        ],
        'zoom' => 12,
    ],
];
