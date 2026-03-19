<?php

namespace App\Observers;

use App\Models\Trip;
use App\Services\RuraZoneService;

class TripObserver
{
    public function creating(Trip $trip)
    {
        $zoneService = app(RuraZoneService::class);
        if ($trip->pickup_lat && $trip->pickup_lng) {
            $trip->pickup_zone = $zoneService->coordsToZone($trip->pickup_lat, $trip->pickup_lng);
        }
        if ($trip->dropoff_lat && $trip->dropoff_lng) {
            $trip->dropoff_zone = $zoneService->coordsToZone($trip->dropoff_lat, $trip->dropoff_lng);
        }
    }

    public function updating(Trip $trip)
    {
        $zoneService = app(RuraZoneService::class);
        if ($trip->pickup_lat && $trip->pickup_lng) {
            $trip->pickup_zone = $zoneService->coordsToZone($trip->pickup_lat, $trip->pickup_lng);
        }
        if ($trip->dropoff_lat && $trip->dropoff_lng) {
            $trip->dropoff_zone = $zoneService->coordsToZone($trip->dropoff_lat, $trip->dropoff_lng);
        }
    }
}
