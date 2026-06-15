<?php

namespace App\Domain\Driver;

use App\Exceptions\DomainException;
use App\Models\Driver;
use App\Models\Trip;
use App\Services\TransportMappingService;

/**
 * DriverPolicy encapsulates all driver-related business rules.
 *
 * Used to validate:
 * - Driver availability for trip acceptance
 * - Vehicle compatibility with ride requirements
 * - Trip assignment eligibility
 */
class DriverPolicy
{
    /**
     * Check if a driver can accept a trip.
     *
     * Validates:
     * - Driver has at least one vehicle
     * - Vehicle type is compatible with ride transport type
     * - Trip is in PENDING state
     */
    public static function canAcceptTrip(Driver $driver, Trip $trip): bool
    {
        // Driver must have a vehicle
        $vehicle = $driver->vehicles()->first();
        if (! $vehicle) {
            return false;
        }

        // Trip must be in REQUESTED/PENDING state
        if ($trip->status !== 'REQUESTED' && $trip->status !== 'PENDING') {
            return false;
        }

        // Trip must identify a transport type, either directly or through a ride.
        $ride = $trip->ride;
        $transportType = $ride?->transport_type ?? $trip->transport_type;
        if (! $transportType) {
            return false;
        }

        // Vehicle type must be compatible with ride transport type
        return TransportMappingService::isCompatible(
            $vehicle->vehicle_type,
            $transportType
        );
    }

    /**
     * Assert that a driver can accept a trip.
     *
     * Throws DomainException if validation fails.
     *
     *
     * @throws DomainException
     */
    public static function assertCanAcceptTrip(Driver $driver, Trip $trip): void
    {
        // Check if driver has a vehicle
        $vehicle = $driver->vehicles()->first();
        if (! $vehicle) {
            throw DomainException::make(
                'Driver must have at least one vehicle to accept trips',
                'NO_VEHICLE_FOUND'
            );
        }

        // Check if trip is in REQUESTED/PENDING state
        if ($trip->status !== 'REQUESTED' && $trip->status !== 'PENDING') {
            throw DomainException::make(
                'This trip is no longer pending',
                'TRIP_NOT_PENDING'
            );
        }

        // Check if trip has a transport type, either directly or through a ride
        $ride = $trip->ride;
        $transportType = $ride?->transport_type ?? $trip->transport_type;
        if (! $transportType) {
            throw DomainException::make(
                'Trip must identify a transport type',
                'NO_TRANSPORT_TYPE_FOUND'
            );
        }

        // Check vehicle compatibility
        if (! TransportMappingService::isCompatible($vehicle->vehicle_type, $transportType)) {
            throw DomainException::make(
                'Vehicle type not compatible with ride transport type',
                'VEHICLE_TYPE_INCOMPATIBLE'
            );
        }
    }

    /**
     * Check if a driver is currently online and available.
     */
    public static function isAvailable(Driver $driver): bool
    {
        return ($driver->availability_status ?? 'offline') === 'online';
    }

    /**
     * Assert that a driver is available.
     *
     *
     * @throws DomainException
     */
    public static function assertAvailable(Driver $driver): void
    {
        if (! self::isAvailable($driver)) {
            throw DomainException::make(
                'Driver must be online to accept trips',
                'DRIVER_NOT_AVAILABLE'
            );
        }
    }

    /**
     * Check if a driver owns a trip (is assigned as the driver).
     */
    public static function ownTrip(Driver $driver, Trip $trip): bool
    {
        return $trip->driver_id === $driver->id;
    }

    /**
     * Assert that a driver owns a trip.
     *
     *
     * @throws DomainException
     */
    public static function assertOwnTrip(Driver $driver, Trip $trip): void
    {
        if (! self::ownTrip($driver, $trip)) {
            throw DomainException::make(
                'This trip is not assigned to you',
                'TRIP_NOT_OWNED_BY_DRIVER'
            );
        }
    }
}
