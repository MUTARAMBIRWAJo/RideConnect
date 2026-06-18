<?php

namespace App\Services\V3;

use App\Models\V3\TripV3;
use App\Models\V3\DriverLocationV3;
use Illuminate\Support\Facades\DB;

class DriverMatchingEngineV3
{
    /**
     * Finds nearby available drivers and assigns the trip to the nearest ones.
     */
    public function findAndNotifyNearbyDrivers(TripV3 $trip, int $limit = 3, int $radiusKm = 10): void
    {
        // Change status to searching
        $trip->update(['status' => 'PENDING_MATCH']);

        $pickupLat = $trip->pickup_lat;
        $pickupLng = $trip->pickup_lng;

        // Use Haversine formula to find nearest online drivers
        $nearestDrivers = DriverLocationV3::select('driver_locations_v3.*')
            ->selectRaw(
                '( 6371 * acos( cos( radians(?) ) * cos( radians( lat ) ) * cos( radians( lng ) - radians(?) ) + sin( radians(?) ) * sin( radians( lat ) ) ) ) AS distance',
                [$pickupLat, $pickupLng, $pickupLat]
            )
            ->join('drivers', 'drivers.id', '=', 'driver_locations_v3.driver_id')
            ->where('driver_locations_v3.is_online', true)
            // Normally we'd filter by drivers.is_available and transport_type here
            // ->where('drivers.is_available', true)
            ->having('distance', '<', $radiusKm)
            ->orderBy('distance')
            ->limit($limit)
            ->get();

        if ($nearestDrivers->isEmpty()) {
            // Expand radius logic or fail
            return;
        }

        // We will assign the trip to the first nearest driver for now.
        // The implementation can be expanded to broadcast to multiple and first to accept wins.
        $topDriver = $nearestDrivers->first();

        $trip->update([
            'driver_id' => $topDriver->driver_id,
            'status' => 'AWAITING_DRIVER_RESPONSE'
        ]);

        // Insert event to trigger Supabase Realtime
        DB::table('trip_events_v3')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'trip_id' => $trip->id,
            'event_type' => 'DRIVER_REQUEST_RECEIVED',
            'payload' => json_encode(['driver_id' => $topDriver->driver_id, 'distance' => $topDriver->distance]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert or update active_trips_v3
        DB::table('active_trips_v3')->updateOrInsert(
            ['trip_id' => $trip->id],
            [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'driver_id' => $topDriver->driver_id,
                'passenger_id' => $trip->user_id,
                'status' => 'AWAITING_DRIVER_RESPONSE',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
