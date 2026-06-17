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

        $query = Driver::query()
            ->select('drivers.*')
            ->selectRaw(
                "( ? * acos( cos( radians(?) ) *
                  cos( radians( current_latitude ) )
                  * cos( radians( current_longitude ) - radians(?)
                  ) + sin( radians(?) ) *
                  sin( radians( current_latitude ) ) )
                ) AS distance", [$earthRadiusKm, $lat, $lng, $lat]
            )
            ->where('status', 'approved')
            ->where('is_active', true)
            ->where('is_online', true)
            ->whereIn('availability_status', ['online', 'available'])
            ->whereNull('current_trip_id')
            ->where('last_seen_at', '>=', now()->subSeconds(30));

        if (!empty($ignoredDriverIds)) {
            $query->whereNotIn('id', $ignoredDriverIds);
        }

        // Additional filter based on transport type would typically happen here
        // or by joining the vehicles table. For simulation, we just return the closest.
        
        return $query->having('distance', '<=', $radiusKm)
            ->orderBy('distance', 'asc')
            ->get();
    }
}
