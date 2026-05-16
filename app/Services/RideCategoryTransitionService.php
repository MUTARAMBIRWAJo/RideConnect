<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Ride;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RideCategoryTransitionService
{
    private const DEFAULT_TRIP_THRESHOLD_HOURS = 6;

    /**
     * A ride in <= 6 hours is treated as a Trip.
     */
    public function isTripCategory(Ride $ride): bool
    {
        if (! $ride->departure_time) {
            return false;
        }

        $hoursToDeparture = now()->diffInMinutes($ride->departure_time, false) / 60;

        return $hoursToDeparture <= $this->thresholdHours();
    }

    /**
     * Convert all eligible bookings to trips.
     */
    public function promoteEligibleBookingsToTrips(?Ride $ride = null): int
    {
        $threshold = now()->copy()->addHours($this->thresholdHours());

        $query = Booking::query()
            ->with(['ride', 'user'])
            ->whereHas('ride', fn ($rideQuery) => $rideQuery->where('departure_time', '<=', $threshold))
            ->whereNotIn('status', ['CANCELLED', 'COMPLETED', 'NO_SHOW', 'cancelled', 'completed', 'no_show']);

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
     * This is for ON_DEMAND CAR and MOTORCYCLE rides only.
     * BUS rides must use booking flow.
     */
    public function createTripFromRideSelection(User $user, Ride $ride, array $payload): Trip
    {
        // ❌ Enforce: BUS rides must use booking flow, not direct trip creation
        if ($ride->isBus()) {
            throw new \InvalidArgumentException('BUS rides must use booking flow, not direct trip creation');
        }

        // Ensure required locations are provided
        $pickupLocation = $payload['pickup_address'] ?? $ride->origin_address;
        $dropoffLocation = $payload['dropoff_address'] ?? $ride->destination_address;

        if (! $pickupLocation || ! $dropoffLocation) {
            throw new \InvalidArgumentException('Pickup and dropoff locations are required for trip creation');
        }

        $passengerId = $this->resolvePassengerMobileUserId($user);

        return Trip::create([
            'booking_id' => null,
            'ride_id' => $ride->id,
            'passenger_id' => $passengerId,
            'driver_id' => $ride->driver_id,
            'pickup_location' => $pickupLocation,
            'pickup_lat' => $payload['pickup_lat'] ?? $ride->origin_lat,
            'pickup_lng' => $payload['pickup_lng'] ?? $ride->origin_lng,
            'dropoff_location' => $dropoffLocation,
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

            $existingTrip = Trip::query()
                ->where('booking_id', $lockedBooking->id)
                ->first();

            if ($existingTrip) {
                return $existingTrip;
            }

            $passengerId = $this->resolvePassengerMobileUserId($lockedBooking->user);

            $trip = Trip::create([
                'booking_id' => $lockedBooking->id,
                'ride_id' => $lockedBooking->ride->id,
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

            $lockedBooking->update([
                'status' => in_array($status, ['completed'], true) ? 'COMPLETED' : 'CONFIRMED',
                'confirmed_at' => $status === 'confirmed' ? ($lockedBooking->confirmed_at ?: now()) : $lockedBooking->confirmed_at,
            ]);

            return $trip;
        });
    }

    /**
     * Convert pending trips back to bookings when departure is outside trip threshold.
     */
    public function demoteEligibleTripsToBookings(?Ride $ride = null): int
    {
        $threshold = now()->copy()->addHours($this->thresholdHours());

        $query = Trip::query()
            ->with('ride')
            ->whereIn('status', ['PENDING', 'pending'])
            ->whereNotNull('ride_id')
            ->whereHas('ride', fn ($rideQuery) => $rideQuery->where('departure_time', '>', $threshold));

        if ($ride) {
            $query->where('ride_id', $ride->id);
        }

        $converted = 0;

        foreach ($query->get() as $trip) {
            if ($this->convertTripToBooking($trip)) {
                $converted++;
            }
        }

        return $converted;
    }

    /**
     * Keep travel category data aligned in both directions.
     *
     * @return array{promoted: int, demoted: int}
     */
    public function synchronizeTravelCategories(?Ride $ride = null): array
    {
        $promoted = $this->promoteEligibleBookingsToTrips($ride);
        $demoted = $this->demoteEligibleTripsToBookings($ride);

        return [
            'promoted' => $promoted,
            'demoted' => $demoted,
        ];
    }

    /**
     * Move one trip row back into bookings when it no longer meets trip timing rules.
     */
    public function convertTripToBooking(Trip $trip): ?Booking
    {
        $trip->loadMissing('ride');

        if (! $trip->ride || $this->isTripCategory($trip->ride)) {
            return null;
        }

        if (! in_array($trip->status, ['PENDING', 'pending'], true)) {
            return null;
        }

        return DB::transaction(function () use ($trip): ?Booking {
            $lockedTrip = Trip::query()
                ->with('ride')
                ->lockForUpdate()
                ->find($trip->id);

            if (! $lockedTrip || ! $lockedTrip->ride || $this->isTripCategory($lockedTrip->ride)) {
                return null;
            }

            if (! in_array($lockedTrip->status, ['PENDING', 'pending'], true)) {
                return null;
            }

            if ($lockedTrip->booking_id) {
                $linkedBooking = Booking::query()->find($lockedTrip->booking_id);

                if ($linkedBooking) {
                    $linkedBooking->update([
                        'status' => 'PENDING',
                    ]);

                    $lockedTrip->update(['booking_id' => null]);
                    $lockedTrip->delete();

                    return $linkedBooking;
                }
            }

            $userId = $this->resolveWebUserIdFromPassengerId((int) $lockedTrip->passenger_id);
            if (! $userId) {
                return null;
            }

            $existingBooking = Booking::query()
                ->where('ride_id', $lockedTrip->ride->id)
                ->where('user_id', $userId)
                ->whereIn('status', ['PENDING', 'CONFIRMED', 'pending', 'confirmed'])
                ->first();

            if ($existingBooking) {
                $lockedTrip->delete();

                return $existingBooking;
            }

            $booking = Booking::create([
                'user_id' => $userId,
                'ride_id' => $lockedTrip->ride->id,
                'seats_booked' => 1,
                'total_price' => (float) ($lockedTrip->actual_fare ?: $lockedTrip->fare ?: $lockedTrip->ride->price_per_seat),
                'currency' => $lockedTrip->ride->currency ?: 'RWF',
                'status' => $this->mapTripStatusToBookingStatus($lockedTrip->status),
                'pickup_address' => $lockedTrip->pickup_location ?: $lockedTrip->ride->origin_address,
                'pickup_lat' => $lockedTrip->pickup_lat ?: $lockedTrip->ride->origin_lat,
                'pickup_lng' => $lockedTrip->pickup_lng ?: $lockedTrip->ride->origin_lng,
                'dropoff_address' => $lockedTrip->dropoff_location ?: $lockedTrip->ride->destination_address,
                'dropoff_lat' => $lockedTrip->dropoff_lat ?: $lockedTrip->ride->destination_lat,
                'dropoff_lng' => $lockedTrip->dropoff_lng ?: $lockedTrip->ride->destination_lng,
                'confirmed_at' => in_array($lockedTrip->status, ['ACCEPTED', 'accepted'], true)
                    ? ($lockedTrip->accepted_at ?: now())
                    : null,
            ]);

            $lockedTrip->delete();

            return $booking;
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

    private function resolveWebUserIdFromPassengerId(int $passengerId): ?int
    {
        if ($passengerId <= 0) {
            return null;
        }

        $userId = User::query()
            ->where('mobile_user_id', $passengerId)
            ->value('id');

        if ($userId) {
            return (int) $userId;
        }

        // Backward-compatible fallback for legacy environments where ids align.
        $legacyUserId = User::query()->where('id', $passengerId)->value('id');

        return $legacyUserId ? (int) $legacyUserId : null;
    }

    private function mapTripStatusToBookingStatus(string $tripStatus): string
    {
        return match (strtolower($tripStatus)) {
            'accepted' => 'CONFIRMED',
            'completed' => 'COMPLETED',
            'cancelled' => 'CANCELLED',
            default => 'PENDING',
        };
    }

    private function thresholdHours(): int
    {
        return (int) config('ride.booking_to_trip_threshold_hours', self::DEFAULT_TRIP_THRESHOLD_HOURS);
    }
}
