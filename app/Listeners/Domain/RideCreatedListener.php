<?php

namespace App\Listeners\Domain;

use App\Events\Domain\RideCreated;
use Illuminate\Support\Facades\Log;
use Throwable;

class RideCreatedListener
{
    public function handle(RideCreated $event): void
    {
        try {
            Log::info('Ride created event received', [
                'ride_id' => $event->rideId,
                'driver_id' => $event->driverId,
            ]);
        } catch (Throwable $throwable) {
            Log::error('RideCreatedListener failed', [
                'ride_id' => $event->rideId,
                'error' => $throwable->getMessage(),
            ]);
        }
    }
}
