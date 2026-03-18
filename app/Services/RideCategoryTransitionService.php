<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Ride;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RideCategoryTransitionService
{
    public const TRIP_THRESHOLD_HOURS = 6;

    /**
     * A ride in <= 6 hours is treated as a Trip.
     */
    public function isTripCategory(Ride $ride): bool
    {
        if (! $ride->departure_time) {
            return false;
        }

        $hoursToDeparture = now()->diffInMinutes($ride->departure_time, false) / 60;

        return $hoursToDeparture <= self::TRIP_THRESHOLD_HOURS;
    }

    /**
     * Convert all eligible bookings to trips.
     */
    public function promoteEligibleBookingsToTrips(?Ride $ride = null): int
    {
        $threshold = now()->copy()->addHours(self::TRIP_THRESHOLD_HOURS);

        $query = Booking::query()
            ->with(['ride', 'user'])
            ->whereHas('ride', fn ($rideQuery) => $rideQuery->where('departure_time', '<=', $threshold))
            ->whereNotIn('status', ['CANCELLED', 'cancelled', 'COMPLETED', 'completed', 'NO_SHOW', 'no_show']);

        if ($ride) {
            $query->where('ride_id', $ride->id);
        }

        $converted = 0;

        foreach ($query->get() as $booking) {
            if ($this->convertBookingToTrip($booking)) {
                $converted++;
            }
        }

        return $converted;
    }

    /**
     * Create a trip directly from passenger booking request when the ride is <= 6h away.
     */
    public function createTripFromRideSelection(User $user, Ride $ride, array $payload): Trip
    {
        $passengerId = $this->resolvePassengerMobileUserId($user);

        return Trip::create([
            'passenger_id' => $passengerId,
            'driver_id' => $ride->driver_id,
            'pickup_location' => $payload['pickup_address'] ?? $ride->origin_address,
            'pickup_lat' => $payload['pickup_lat'] ?? $ride->origin_lat,
            'pickup_lng' => $payload['pickup_lng'] ?? $ride->origin_lng,
            'dropoff_location' => $payload['dropoff_address'] ?? $ride->destination_address,
            'dropoff_lat' => $payload['dropoff_lat'] ?? $ride->destination_lat,
            'dropoff_lng' => $payload['dropoff_lng'] ?? $ride->destination_lng,
            'fare' => $payload['total_price'] ?? ($ride->price_per_seat * (int) ($payload['seats_booked'] ?? $payload['seats'] ?? 1)),
            'status' => 'PENDING',
            'requested_at' => now(),
        ]);
    }

    /**
     * Move one booking row into trips and delete the booking row.
     */
    public function convertBookingToTrip(Booking $booking): ?Trip
    {
        $booking->loadMissing(['ride', 'user']);

        if (! $booking->ride || ! $this->isTripCategory($booking->ride)) {
            return null;
        }

        $normalizedStatus = strtolower((string) $booking->status);
        if (in_array($normalizedStatus, ['cancelled', 'completed', 'no_show'], true)) {
            return null;
        }

        return DB::transaction(function () use ($booking): ?Trip {
            $lockedBooking = Booking::query()
                ->with(['ride', 'user'])
                ->lockForUpdate()
                ->find($booking->id);

            if (! $lockedBooking || ! $lockedBooking->ride) {
                return null;
            }

            if (! $this->isTripCategory($lockedBooking->ride)) {
                return null;
            }

            $status = strtolower((string) $lockedBooking->status);
            if (in_array($status, ['cancelled', 'completed', 'no_show'], true)) {
                return null;
            }

            $passengerId = $this->resolvePassengerMobileUserId($lockedBooking->user);

            $trip = Trip::create([
                'passenger_id' => $passengerId,
                'driver_id' => $lockedBooking->ride->driver_id,
                'pickup_location' => $lockedBooking->pickup_address ?: $lockedBooking->ride->origin_address,
                'pickup_lat' => $lockedBooking->pickup_lat ?: $lockedBooking->ride->origin_lat,
                'pickup_lng' => $lockedBooking->pickup_lng ?: $lockedBooking->ride->origin_lng,
                'dropoff_location' => $lockedBooking->dropoff_address ?: $lockedBooking->ride->destination_address,
                'dropoff_lat' => $lockedBooking->dropoff_lat ?: $lockedBooking->ride->destination_lat,
                'dropoff_lng' => $lockedBooking->dropoff_lng ?: $lockedBooking->ride->destination_lng,
                'fare' => $lockedBooking->total_price,
                'status' => $this->mapBookingStatusToTripStatus($lockedBooking->status),
                'requested_at' => $lockedBooking->created_at,
                'accepted_at' => $status === 'confirmed' ? ($lockedBooking->confirmed_at ?: now()) : null,
                'completed_at' => $status === 'completed' ? ($lockedBooking->updated_at ?: now()) : null,
            ]);

            $lockedBooking->delete();

            return $trip;
        });
    }

    private function mapBookingStatusToTripStatus(string $bookingStatus): string
    {
        return match (strtolower($bookingStatus)) {
            'confirmed' => 'ACCEPTED',
            'completed' => 'COMPLETED',
            'cancelled' => 'CANCELLED',
            default => 'PENDING',
        };
    }

    private function resolvePassengerMobileUserId(?User $user): int
    {
        if (! $user) {
            throw new \InvalidArgumentException('Cannot create trip without an associated user.');
        }

        if ($user->mobile_user_id) {
            return (int) $user->mobile_user_id;
        }

        // Backward-compatible fallback for environments where IDs were historically aligned.
        return (int) $user->id;
    }
}
