<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Ride;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

/**
 * DriverAssignmentService handles automatic driver assignment for trips.
 *
 * Selects the best available driver based on:
 * - Vehicle compatibility with ride transport type
 * - Geographic proximity (nearest to pickup)
 * - Availability status
 * - Active status
 */
class DriverAssignmentService
{
    /**
     * Find and auto-assign the best available driver for a trip.
     *
     * Selection criteria (in order):
     * 1. Vehicle must be compatible with ride transport type
     * 2. Driver must be 'approved' status
     * 3. Vehicle must be 'is_active'
     * 4. Nearest to pickup location (by distance)
     * 5. Availability status 'available'
     *
     * @param Trip $trip
     * @param Ride $ride
     * @return Driver|null The best available driver, or null if none found
     */
    public function findBestDriver(Trip $trip, Ride $ride): ?Driver
    {
        $vehicleTypes = $this->getVehicleTypesForTransport($ride->transport_type);

        $query = Driver::query()
            ->with('vehicles')
            ->join('users', 'drivers.user_id', '=', 'users.id')
            ->leftJoin('driver_locations', 'users.mobile_user_id', '=', 'driver_locations.driver_id')
            ->where('drivers.status', 'approved')
            ->whereHas('vehicles', function ($q) use ($vehicleTypes) {
                $q->where('is_active', true)
                  ->whereIn('vehicle_type', $vehicleTypes);
            });

        // Filter by proximity if coordinates are available
        if ($trip->pickup_lat && $trip->pickup_lng && $ride->isOnDemand()) {
            $query->whereNotNull('driver_locations.latitude');
            $query = $this->orderByProximity($query, $trip->pickup_lat, $trip->pickup_lng);
        }

        return $query->first();
    }

    /**
     * Get compatible vehicle types for a transport type.
     */
    private function getVehicleTypesForTransport(string $transportType): array
    {
        return TransportMappingService::getVehicleTypesFor($transportType);
    }

    /**
     * Order drivers by distance to pickup location.
     *
     * Uses Haversine formula to calculate distance.
     * Closest drivers appear first.
     *
     * @param mixed $query
     * @param float $lat
     * @param float $lng
     * @return mixed
     */
    private function orderByProximity($query, float $lat, float $lng)
    {
        return $query->orderByRaw(
            "
            (
                6371 * acos(
                    cos(radians(?)) * cos(radians(driver_locations.latitude)) *
                    cos(radians(driver_locations.longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(driver_locations.latitude))
                )
            ) ASC
            ",
            [$lat, $lng, $lat]
        );
    }

    /**
     * Check if a driver is available for assignment.
     *
     * @param Driver $driver
     * @param Ride $ride
     * @return bool
     */
    public function isDriverAvailable(Driver $driver, Ride $ride): bool
    {
        // Driver must be approved
        if ($driver->status !== 'approved') {
            return false;
        }

        // Driver must have at least one compatible active vehicle
        $compatibleVehicle = $driver->vehicles()
            ->where('is_active', true)
            ->get()
            ->first(fn($v) => TransportMappingService::isCompatible($v->vehicle_type, $ride->transport_type));

        return $compatibleVehicle !== null;
    }

    /**
     * Assign a specific driver to a trip.
     *
     * Updates trip driver_id and returns the updated trip.
     *
     * @param Trip $trip
     * @param Driver $driver
     * @return Trip
     */
    public function assignDriver(Trip $trip, Driver $driver): Trip
    {
        $trip->update(['driver_id' => $driver->id]);
        return $trip->fresh();
    }

    /**
     * Auto-assign the best available driver to a trip.
     *
     * Returns the updated trip with driver assigned, or null if no driver available.
     *
     * @param Trip $trip
     * @param Ride $ride
     * @return Trip|null
     */
    public function autoAssign(Trip $trip, Ride $ride): ?Trip
    {
        $driver = $this->findBestDriver($trip, $ride);

        if (!$driver) {
            return null;
        }

        return $this->assignDriver($trip, $driver);
    }
}
