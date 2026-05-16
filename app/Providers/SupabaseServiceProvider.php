<?php

namespace App\Providers;

use App\Services\SupabaseClient;
use Illuminate\Support\ServiceProvider;

class SupabaseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(SupabaseClient::class, function ($app) {
            return new SupabaseClient(
                config('supabase.url'),
                config('supabase.key'),
                config('supabase.service_role_key')
            );
        });

        $this->app->alias(SupabaseClient::class, 'supabase.client');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
