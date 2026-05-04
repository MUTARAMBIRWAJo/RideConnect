<?php

namespace App\Domain\Ride;

use App\Exceptions\DomainException;
use App\Models\Ride;

/**
 * RidePolicy is the SINGLE SOURCE OF TRUTH for all ride business rules.
 *
 * This ensures:
 * - Consistent behavior across controllers, APIs, and dashboards
 * - Rules are centralized, not scattered
 * - Flutter and Admin systems get identical rule enforcement
 */
class RidePolicy
{
    /**
     * Constant: Only booking is allowed on this ride.
     */
    public const FLOW_BOOKING_ONLY = 'BOOKING_ONLY';

    /**
     * Constant: Only trip request is allowed on this ride.
     */
    public const FLOW_TRIP_ONLY = 'TRIP_ONLY';

    /**
     * Constant: Both booking and trip are allowed (CAR SCHEDULED can allow both).
     */
    public const FLOW_BOTH = 'BOTH';

    /**
     * Constant: Neither booking nor trip is allowed.
     */
    public const FLOW_NONE = 'NONE';

    /**
     * Determine if a ride can accept bookings.
     *
     * ONLY SCHEDULED rides can be booked.
     *
     * @param Ride $ride
     * @return bool
     */
    public static function canBook(Ride $ride): bool
    {
        if ($ride->isBus()) {
            return $ride->isScheduled() && (int) $ride->route_id > 0;
        }

        return $ride->isScheduled();
    }

    /**
     * Determine if a ride can accept trip requests.
     *
     * ONLY ON_DEMAND rides can accept trip requests.
     *
     * @param Ride $ride
     * @return bool
     */
    public static function canRequestTrip(Ride $ride): bool
    {
        return $ride->isOnDemand();
    }

    /**
     * Get the allowed passenger flow for this ride.
     *
     * Maps transport_type and travel_mode to the allowed action:
     * - BUS (SCHEDULED) → BOOKING_ONLY
     * - CAR (SCHEDULED) → BOOKING_ONLY
     * - CAR (ON_DEMAND) → TRIP_ONLY
     * - MOTORCYCLE (ON_DEMAND) → TRIP_ONLY
     *
     * @param Ride $ride
     * @return string One of: BOOKING_ONLY, TRIP_ONLY, BOTH, NONE
     */
    public static function getAllowedFlow(Ride $ride): string
    {
        if (!$ride->transport_type || !$ride->travel_mode) {
            return self::FLOW_NONE;
        }

        if ($ride->isBus()) {
            return $ride->isScheduled() && (int) $ride->route_id > 0
                ? self::FLOW_BOOKING_ONLY
                : self::FLOW_NONE;
        }

        // SCHEDULED rides allow booking
        if ($ride->isScheduled()) {
            // CAR can be booked (CAR is flexible)
            if ($ride->isCar()) {
                return self::FLOW_BOOKING_ONLY;
            }

            // MOTORCYCLE cannot be scheduled (validation prevents this)
            return self::FLOW_NONE;
        }

        // ON_DEMAND rides allow trip requests
        if ($ride->isOnDemand()) {
            // CAR can allow trip requests
            if ($ride->isCar()) {
                return self::FLOW_TRIP_ONLY;
            }

            // MOTORCYCLE can only accept trip requests
            if ($ride->isMotorcycle()) {
                return self::FLOW_TRIP_ONLY;
            }

            // BUS cannot be on-demand (validation prevents this)
            return self::FLOW_NONE;
        }

        return self::FLOW_NONE;
    }

    /**
     * Check if a ride has available seats for reservation.
     *
     * @param Ride $ride
     * @param int $seats
     * @return bool
     */
    public static function canReserveSeats(Ride $ride, int $seats): bool
    {
        self::assertSeatIntegrity($ride);

        if ($seats <= 0) {
            return false;
        }

        return ($ride->available_seats ?? 0) >= $seats;
    }

    /**
     * Assert that booking is allowed on this ride.
     *
     * Throws DomainException if booking is not allowed.
     *
     * @param Ride $ride
     * @return void
     *
     * @throws DomainException
     */
    public static function assertBookingAllowed(Ride $ride): void
    {
        if (!self::canBook($ride)) {
            throw DomainException::make(
                'Bookings are only allowed on SCHEDULED rides',
                'BOOKING_NOT_ALLOWED_FOR_TRAVEL_MODE'
            );
        }
    }

    /**
     * Assert that trip requests are allowed on this ride.
     *
     * Throws DomainException if trip requests are not allowed.
     *
     * @param Ride $ride
     * @return void
     *
     * @throws DomainException
     */
    public static function assertTripAllowed(Ride $ride): void
    {
        if (!self::canRequestTrip($ride)) {
            throw DomainException::make(
                'Trip requests are only allowed on ON_DEMAND rides',
                'TRIP_NOT_ALLOWED_FOR_TRAVEL_MODE'
            );
        }
    }

    /**
     * Assert that a BUS ride follows the public transport policy.
     *
     * BUS rides must always be SCHEDULED and bound to a route.
     *
     * @param Ride $ride
     * @return void
     *
     * @throws DomainException
     */
    public static function assertBusRules(Ride $ride): void
    {
        if (! $ride->isBus()) {
            return;
        }

        if (! $ride->isScheduled()) {
            throw DomainException::make(
                'BUS rides must use SCHEDULED travel mode',
                'BUS_TRAVEL_MODE_REQUIRED'
            );
        }

        if (! $ride->route_id) {
            throw DomainException::make(
                'BUS rides must be linked to a route',
                'BUS_ROUTE_REQUIRED'
            );
        }
    }

    /**
     * Assert that sufficient seats are available for reservation.
     *
     * Throws DomainException if not enough seats.
     *
     * @param Ride $ride
     * @param int $seats
     * @return void
     *
     * @throws DomainException
     */
    public static function assertSeatsAvailable(Ride $ride, int $seats): void
    {
        self::assertSeatIntegrity($ride);

        if (!self::canReserveSeats($ride, $seats)) {
            throw DomainException::make(
                sprintf(
                    'Insufficient seats. Requested %d but only %d available',
                    $seats,
                    $ride->available_seats ?? 0
                ),
                'INSUFFICIENT_SEATS'
            );
        }
    }

    /**
     * Ensure seat counters are never negative or malformed.
     */
    public static function assertSeatIntegrity(Ride $ride): void
    {
        $availableSeats = (int) ($ride->available_seats ?? 0);

        if ($availableSeats < 0) {
            throw DomainException::make(
                'Seat integrity violation: available seats cannot be negative',
                'SEAT_INTEGRITY_VIOLATION'
            );
        }
    }

    /**
     * Get ride rules as an array for API responses.
     *
     * This is the contract that Flutter consumes to determine allowed actions.
     *
     * @param Ride $ride
     * @return array
     */
    public static function toApiRules(Ride $ride): array
    {
        $flow = self::getAllowedFlow($ride);

        return [
            'can_book' => self::canBook($ride),
            'can_request_trip' => self::canRequestTrip($ride),
            'allowed_flow' => $flow,
        ];
    }

    /**
     * Validate that ride_type is valid.
     *
     * @param Ride $ride
     * @return void
     *
     * @throws DomainException
     */
    public static function assertValidRideType(Ride $ride): void
    {
        if (!in_array($ride->ride_type, [Ride::TYPE_INTERCITY, Ride::TYPE_LOCAL], true)) {
            throw DomainException::make(
                'Invalid ride type: ' . $ride->ride_type,
                'INVALID_RIDE_TYPE'
            );
        }
    }

    /**
     * Backward-compatible alias.
     */
    public static function getRulesForApi(Ride $ride): array
    {
        return self::toApiRules($ride);
    }
}
