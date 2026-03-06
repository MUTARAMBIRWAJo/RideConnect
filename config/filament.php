<?php

return [
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
