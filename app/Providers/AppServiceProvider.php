<?php

namespace App\Providers;

use App\Auth\SafeEloquentUserProvider;
use App\Domain\Core\DomainEventRegistry;
use App\Models\DriverPayout;
use App\Models\FraudFlag;
use App\Models\LedgerEntry;
use App\Models\Manager;
use App\Models\MobileUser;
use App\Models\BusRouteAssignment;
use App\Models\PassengerRouteBoarding;
use App\Models\Ride;
use App\Models\TransportCorridor;
use App\Models\Trip;
use App\Models\User;
use App\Observers\TripObserver;
use App\Policies\DriverPayoutPolicy;
use App\Policies\FraudFlagPolicy;
use App\Policies\LedgerPolicy;
use App\Policies\ManagerPolicy;
use App\Policies\MobileUserPolicy;
use App\Policies\BusRouteAssignmentPolicy;
use App\Policies\PassengerRouteBoardingPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RidePolicy;
use App\Policies\RolePolicy;
use App\Policies\TransportCorridorPolicy;
use App\Policies\TripPolicy;
use App\Policies\UserPolicy;
use App\Services\DatabaseTableProtectionService;
use App\Services\RoleAccessService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
// Register SafeEloquentUserProvider class
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register the RoleAccessService as a singleton
        $this->app->singleton(RoleAccessService::class, function ($app) {
            return new RoleAccessService;
        });

        $this->app->singleton(\App\Services\TfliteMatchingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production')) {
            try {
                app(\App\Services\TfliteMatchingService::class)->warmUp();
            } catch (\Throwable) {
                // Never let ML warmup crash the app.
            }
        }

        // Register Trip observer for zone assignment
        Trip::observe(TripObserver::class);

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        app(DatabaseTableProtectionService::class)->register();

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
        Gate::policy(Ride::class, RidePolicy::class);
        Gate::policy(Trip::class, TripPolicy::class);
        Gate::policy(TransportCorridor::class, TransportCorridorPolicy::class);
        Gate::policy(BusRouteAssignment::class, BusRouteAssignmentPolicy::class);
        Gate::policy(PassengerRouteBoarding::class, PassengerRouteBoardingPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(DriverPayout::class, DriverPayoutPolicy::class);
        Gate::policy(FraudFlag::class, FraudFlagPolicy::class);
        Gate::policy(LedgerEntry::class, LedgerPolicy::class);

        foreach (DomainEventRegistry::listeners() as $eventClass => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($eventClass, $listener);
            }
        }

        // Register a safe eloquent user provider that returns null
        // when the database is unreachable, preventing 500 errors
        // during authentication checks (useful for local offline dev).
        Auth::provider('eloquent_safe', function ($app, array $config) {
            return new SafeEloquentUserProvider($app['hash'], $config['model']);
        });
    }
}
