<?php

namespace App\Providers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /*
         * Channel authorization — called by Reverb / Echo when Flutter
         * subscribes to private-{channel}. Each subscriber sends the
         * sanctum token; we decode it and confirm the user owns the
         * resource (their trip, their driver id, their user id).
         *
         * If realtime is not enabled (REALTIME_ENABLED=false), Reverb
         * never starts, so these gates are never called — zero overhead.
         */
        Gate::define('view-trip', function ($user, int $tripId) {
            try {
                $trip = \App\Models\MotorcycleTrip::query()->find($tripId);
                if (! $trip) {
                    throw new AuthorizationException('Trip not found');
                }
                // Owner (passenger) or assigned driver may view.
                if ((int) $trip->passenger_id === (int) $user->id) {
                    return true;
                }
                if ((int) $trip->driver_id === (int) $user->id) {
                    return true;
                }
                throw new AuthorizationException('Not authorized for this trip');
            } catch (\Throwable $e) {
                Log::warning('Channel auth failed', [
                    'user_id' => $user->id ?? null,
                    'trip_id' => $tripId,
                    'error' => $e->getMessage(),
                ]);
                throw new AuthorizationException($e->getMessage());
            }
        });

        Gate::define('view-driver-channel', function ($user, int $driverId) {
            // A driver may only subscribe to their own channel.
            if ((int) $user->driver?->id !== (int) $driverId) {
                throw new AuthorizationException('Not your driver channel');
            }
            return true;
        });

        Gate::define('view-user-channel', function ($user, int $userId) {
            if ((int) $user->id !== (int) $userId) {
                throw new AuthorizationException('Not your user channel');
            }
            return true;
        });
    }
}
