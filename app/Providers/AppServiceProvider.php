<?php

namespace App\Providers;

use App\Models\User;
use App\Models\MobileUser;
use App\Models\Manager;
use App\Policies\UserPolicy;
use App\Policies\MobileUserPolicy;
use App\Policies\ManagerPolicy;
use App\Policies\RolePolicy;
use App\Policies\PermissionPolicy;
use App\Policies\DriverPayoutPolicy;
use App\Policies\FraudFlagPolicy;
use App\Policies\LedgerPolicy;
use App\Services\RoleAccessService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\DriverPayout;
use App\Models\FraudFlag;
use App\Models\LedgerEntry;

// Register SafeEloquentUserProvider class
use App\Auth\SafeEloquentUserProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register the RoleAccessService as a singleton
        $this->app->singleton(RoleAccessService::class, function ($app) {
            return new RoleAccessService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Filament/Livewire serializes all component data via json_encode with
        // JSON_THROW_ON_ERROR. Any string attribute containing non-UTF-8 bytes
        // (e.g. legacy data migrated from Latin-1) will cause a JsonException.
        // This global retrieved observer scrubs every string attribute on the
        // models that feed Filament table columns, replacing bad bytes with
        // the Unicode replacement character (U+FFFD) via mb_scrub().
        $modelsWithFreeText = [
            \App\Models\LedgerEntry::class,
            \App\Models\LedgerTransaction::class,
            \App\Models\FraudFlag::class,
            \App\Models\DriverPayout::class,
        ];

        foreach ($modelsWithFreeText as $modelClass) {
            $modelClass::retrieved(static function ($model) {
                foreach ($model->getAttributes() as $key => $value) {
                    if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
                        $model->setAttribute($key, mb_scrub($value));
                    }
                }
            });
        }
        // Register policies
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(MobileUser::class, MobileUserPolicy::class);
        Gate::policy(Manager::class, ManagerPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(DriverPayout::class, DriverPayoutPolicy::class);
        Gate::policy(FraudFlag::class, FraudFlagPolicy::class);
        Gate::policy(LedgerEntry::class, LedgerPolicy::class);

        // Register a safe eloquent user provider that returns null
        // when the database is unreachable, preventing 500 errors
        // during authentication checks (useful for local offline dev).
        Auth::provider('eloquent_safe', function ($app, array $config) {
            return new SafeEloquentUserProvider($app['hash'], $config['model']);
        });
    }
}
