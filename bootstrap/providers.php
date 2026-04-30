<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\AccountantPanelProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\Filament\OfficerPanelProvider::class,
    App\Providers\FinanceServiceProvider::class,
    App\Providers\Illuminate\Foundation\Providers\FormRequestServiceProvider::class,
    App\Providers\SupabaseServiceProvider::class,
    Ttanai\Laramap\LaramapServiceProvider::class,
];
