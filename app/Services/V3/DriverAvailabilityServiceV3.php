<?php

namespace App\Services\V3;

use App\Models\Driver;

class DriverAvailabilityServiceV3
{
    /**
     * Get nearby available drivers.
     * Uses Haversine formula for distance calculation.
     */
    public function getNearbyAvailableDrivers(float $lat, float $lng, float $radiusKm, string $transportType, array $ignoredDriverIds = []): \Illuminate\Support\Collection
    {
        $earthRadiusKm = 6371;

        $haversine = "( ? * acos( cos( radians(?) ) *
                  cos( radians( current_latitude ) )
                  * cos( radians( current_longitude ) - radians(?)
                  ) + sin( radians(?) ) *
                  sin( radians( current_latitude ) ) )
                )";

        $query = Driver::query()
            ->select('drivers.*')
            ->selectRaw("{$haversine} AS distance", [$earthRadiusKm, $lat, $lng, $lat])
            ->whereRaw("{$haversine} <= ?", [$earthRadiusKm, $lat, $lng, $lat, $radiusKm])
            ->where('status', 'approved')
            ->where('is_active', true)
            ->where('is_online', true)
            ->whereIn('availability_status', ['online', 'available'])
            ->whereNull('current_trip_id')
            ->where('last_seen_at', '>=', now()->subSeconds(30));

        if (!empty($ignoredDriverIds)) {
            $query->whereNotIn('id', $ignoredDriverIds);
        }

        $query->join('vehicles', 'vehicles.driver_id', '=', 'drivers.id');

        if ($transportType === 'motor_vehicle') {
            $query->whereIn('vehicles.vehicle_type', ['motorcycle', 'boda', 'moto', 'motorbike', 'tuk-tuk']);
        } elseif ($transportType === 'public_bus') {
            $query->whereIn('vehicles.vehicle_type', ['bus', 'BUS', 'minibus', 'coach']);
        } elseif ($transportType === 'private_car') {
            $query->whereIn('vehicles.vehicle_type', ['sedan', 'suv', 'hatchback', 'van', 'compact', 'minivan']);
        }

        return $query->orderBy('distance', 'asc')->get();
    }
}
