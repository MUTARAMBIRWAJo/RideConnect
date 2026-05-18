<?php

namespace App\Services;

use App\Exceptions\DomainException;
use App\Models\Booking;
use App\Models\Ride;
use App\Models\SeatReservation;
use App\Models\Trip;
use Illuminate\Support\Facades\DB;

class SeatReservationService
{
    public function reserveForBooking(int $rideId, int $seats, ?int $passengerId = null, ?int $bookingId = null): SeatReservation
    {
        return DB::transaction(function () use ($rideId, $seats, $passengerId, $bookingId): SeatReservation {
            $ride = Ride::query()->lockForUpdate()->findOrFail($rideId);

            if ($seats < 1) {
                throw DomainException::make('At least one seat is required', 'INVALID_SEAT_COUNT');
            }

            if ((int) $ride->available_seats < $seats) {
                throw DomainException::make('Insufficient seats', 'INSUFFICIENT_SEATS');
            }

            $ride->decrement('available_seats', $seats);

            return SeatReservation::query()->create([
                'ride_id' => $ride->id,
                'booking_id' => $bookingId,
                'passenger_id' => $passengerId,
                'seats' => $seats,
                'status' => SeatReservation::STATUS_RESERVED,
                'reserved_at' => now(),
            ]);
        });
    }

    public function attachTrip(Booking $booking, Trip $trip): void
    {
        SeatReservation::query()
            ->where('booking_id', $booking->id)
            ->where('status', SeatReservation::STATUS_RESERVED)
            ->update(['trip_id' => $trip->id]);
    }

    public function releaseForBooking(int $bookingId): int
    {
        return DB::transaction(function () use ($bookingId): int {
            $reservations = SeatReservation::query()
                ->where('booking_id', $bookingId)
                ->where('status', SeatReservation::STATUS_RESERVED)
                ->lockForUpdate()
                ->get();

            $released = 0;
            foreach ($reservations as $reservation) {
                $ride = Ride::query()->lockForUpdate()->find($reservation->ride_id);
                if ($ride) {
                    $ride->increment('available_seats', (int) $reservation->seats);
                }

                $reservation->update([
                    'status' => SeatReservation::STATUS_RELEASED,
                    'released_at' => now(),
                ]);
                $released += (int) $reservation->seats;
            }

            return $released;
        });
    }

    public function consumeForTrip(int $tripId): int
    {
        return DB::transaction(function () use ($tripId): int {
            $reservations = SeatReservation::query()
                ->where('trip_id', $tripId)
                ->where('status', SeatReservation::STATUS_RESERVED)
                ->lockForUpdate()
                ->get();

            $consumed = 0;
            foreach ($reservations as $reservation) {
                $reservation->update(['status' => SeatReservation::STATUS_CONSUMED]);
                $consumed += (int) $reservation->seats;
            }

            return $consumed;
        });
    }

    public function restoreForTrip(int $tripId): int
    {
        return DB::transaction(function () use ($tripId): int {
            $reservations = SeatReservation::query()
                ->where('trip_id', $tripId)
                ->whereIn('status', [SeatReservation::STATUS_RESERVED, SeatReservation::STATUS_CONSUMED])
                ->lockForUpdate()
                ->get();

            $restored = 0;
            foreach ($reservations as $reservation) {
                if ($reservation->status === SeatReservation::STATUS_RELEASED) {
                    continue;
                }

                $ride = Ride::query()->lockForUpdate()->find($reservation->ride_id);
                if ($ride) {
                    $ride->increment('available_seats', (int) $reservation->seats);
                }

                $reservation->update([
                    'status' => SeatReservation::STATUS_RELEASED,
                    'released_at' => now(),
                ]);
                $restored += (int) $reservation->seats;
            }

            return $restored;
        });
    }
}
