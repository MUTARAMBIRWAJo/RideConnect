<?php

return [
    'app_url' => env('APP_URL'),

    'admin_url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/admin',

    'domain' => parse_url((string) env('APP_URL', ''), PHP_URL_HOST),

    /**
     * Panel providers to register for Filament.
     *
     * Keep an array of PanelProvider classes. Our AdminPanelProvider
     * is placed at App\Providers\Filament\AdminPanelProvider::class
     */
    'panels' => [
        App\Providers\Filament\AdminPanelProvider::class,
    ],

    // Optional: other Filament config keys may be added here as needed.
];
