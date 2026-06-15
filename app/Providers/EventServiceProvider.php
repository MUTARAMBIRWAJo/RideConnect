<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // Trip Events - Unified Firebase Sync
        \App\Events\Domain\TripMatched::class => [
            \App\Listeners\BroadcastTripEvents::class,
            \App\Listeners\Firebase\UnifiedFirebaseSyncListener::class,
        ],
        \App\Events\Domain\TripStarted::class => [
            \App\Listeners\BroadcastTripEvents::class,
            \App\Listeners\Firebase\UnifiedFirebaseSyncListener::class,
        ],
        \App\Events\Domain\TripCompleted::class => [
            \App\Listeners\BroadcastTripEvents::class,
            \App\Listeners\Firebase\UnifiedFirebaseSyncListener::class,
        ],
        
        // Motorcycle Trip Events - Unified Firebase Sync
        \App\Events\MotorcycleTripStarted::class => [
            \App\Listeners\Firebase\UnifiedFirebaseSyncListener::class,
        ],
        \App\Events\MotorcycleDriverArrived::class => [
            \App\Listeners\Firebase\UnifiedFirebaseSyncListener::class,
        ],
        \App\Events\MotorcycleTripCompleted::class => [
            \App\Listeners\Firebase\UnifiedFirebaseSyncListener::class,
        ],
        
        // Payment Events - Unified Firebase Sync
        \App\Events\Domain\PaymentVerified::class => [
            \App\Listeners\Firebase\UnifiedFirebaseSyncListener::class,
        ],
        
        // Driver Location Events - Unified Firebase Sync
        \App\Events\DriverLocationUpdated::class => [
            \App\Listeners\Firebase\UnifiedFirebaseSyncListener::class,
        ],
        
        \App\Models\Review::class => [
            \App\Listeners\Firebase\UnifiedFirebaseSyncListener::class,
        ],
        
        // Driver Presence Events
        \App\Events\DriverOnline::class => [
            \App\Listeners\UpdateDriverPresenceListener::class,
        ],
        \App\Events\DriverOffline::class => [
            \App\Listeners\UpdateDriverPresenceListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
