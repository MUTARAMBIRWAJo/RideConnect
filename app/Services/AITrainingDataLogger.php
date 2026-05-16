<?php

namespace App\Services;

use App\Models\Trip;
use Illuminate\Support\Facades\DB;

class AITrainingDataLogger
{
    public function logRideRequest(Trip $trip): void
    {
        if (! DB::getSchemaBuilder()->hasTable('ride_requests')) {
            return;
        }

        DB::table('ride_requests')->insert([
            'trip_id' => $trip->id,
            'passenger_id' => $trip->passenger_id,
            'pickup_lat' => $trip->pickup_lat,
            'pickup_lng' => $trip->pickup_lng,
            'dropoff_lat' => $trip->dropoff_lat,
            'dropoff_lng' => $trip->dropoff_lng,
            'request_time' => $trip->requested_at ?? $trip->created_at ?? now(),
            'status' => $trip->status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->syncRideSnapshot($trip);
        $this->logTripEvent($trip, 'ride_requested');
        $this->logDemand($trip);
    }

    public function logTripEvent(Trip $trip, string $eventType, array $metadata = []): void
    {
        if (! DB::getSchemaBuilder()->hasTable('ride_events')) {
            return;
        }

        DB::table('ride_events')->insert([
            'trip_id' => $trip->id,
            'driver_id' => $trip->driver_id,
            'passenger_id' => $trip->passenger_id,
            'event_type' => $eventType,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'event_time' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function logTripCancellation(Trip $trip, ?int $cancelledBy = null, ?string $reason = null): void
    {
        if (DB::getSchemaBuilder()->hasTable('ride_cancellations')) {
            DB::table('ride_cancellations')->insert([
                'trip_id' => $trip->id,
                'driver_id' => $trip->driver_id,
                'passenger_id' => $trip->passenger_id,
                'cancelled_by_user_id' => $cancelledBy,
                'reason' => $reason,
                'cancelled_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->logTripEvent($trip, 'ride_cancelled', ['reason' => $reason]);
        $this->syncRideSnapshot($trip);
    }

    public function logDriverLocation(int $driverId, float $lat, float $lng, ?float $speedKmh = null, ?float $heading = null): void
    {
        if (! DB::getSchemaBuilder()->hasTable('driver_locations')) {
            return;
        }

        $updatePayload = [
            'latitude' => $lat,
            'longitude' => $lng,
            'updated_at' => now(),
        ];

        if (DB::getSchemaBuilder()->hasColumn('driver_locations', 'speed_kmh')) {
            $updatePayload['speed_kmh'] = $speedKmh;
        }

        if (DB::getSchemaBuilder()->hasColumn('driver_locations', 'heading')) {
            $updatePayload['heading'] = $heading;
        }

        DB::table('driver_locations')->updateOrInsert(
            ['driver_id' => $driverId],
            $updatePayload
        );
    }

    public function logPassengerLocation(int $passengerId, float $lat, float $lng): void
    {
        if (! DB::getSchemaBuilder()->hasTable('passenger_locations')) {
            return;
        }

        DB::table('passenger_locations')->updateOrInsert(
            ['passenger_id' => $passengerId],
            [
                'latitude' => $lat,
                'longitude' => $lng,
                'updated_at' => now(),
            ]
        );
    }

    public function syncRideSnapshot(Trip $trip): void
    {
        if (! DB::getSchemaBuilder()->hasTable('rides')) {
            return;
        }

        $requestTime = $trip->requested_at ?? $trip->created_at;
        $assignedTime = $trip->accepted_at;
        $pickupTime = $trip->started_at;
        $dropoffTime = $trip->completed_at;

        $rideDuration = null;
        if ($pickupTime && $dropoffTime) {
            $rideDuration = max(0, $dropoffTime->diffInSeconds($pickupTime));
        }

        $distance = $trip->actual_distance;
        if ($distance === null && $trip->pickup_lat && $trip->pickup_lng && $trip->dropoff_lat && $trip->dropoff_lng) {
            $distance = $this->haversineDistance(
                (float) $trip->pickup_lat,
                (float) $trip->pickup_lng,
                (float) $trip->dropoff_lat,
                (float) $trip->dropoff_lng,
            );
        }

        if (! DB::table('rides')->where('id', $trip->id)->exists()) {
            return;
        }

        DB::table('rides')->where('id', $trip->id)->update([
            'pickup_lat' => $trip->pickup_lat,
            'pickup_lng' => $trip->pickup_lng,
            'dropoff_lat' => $trip->dropoff_lat,
            'dropoff_lng' => $trip->dropoff_lng,
            'request_time' => $requestTime,
            'driver_assigned_time' => $assignedTime,
            'pickup_time' => $pickupTime,
            'dropoff_time' => $dropoffTime,
            'ride_duration' => $rideDuration,
            'ride_distance' => $distance,
            'ride_status' => strtolower((string) $trip->status),
            'updated_at' => now(),
        ]);
    }

    public function logDemand(Trip $trip): void
    {
        if (! DB::getSchemaBuilder()->hasTable('demand_logs')) {
            return;
        }

        $latBin = $trip->pickup_lat !== null ? round((float) $trip->pickup_lat, 2) : null;
        $lngBin = $trip->pickup_lng !== null ? round((float) $trip->pickup_lng, 2) : null;

        DB::table('demand_logs')->insert([
            'trip_id' => $trip->id,
            'zone_key' => $latBin !== null && $lngBin !== null ? $latBin.':'.$lngBin : null,
            'pickup_lat' => $trip->pickup_lat,
            'pickup_lng' => $trip->pickup_lng,
            'request_time' => $trip->requested_at ?? now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadiusKm * $c, 3);
    }
}
