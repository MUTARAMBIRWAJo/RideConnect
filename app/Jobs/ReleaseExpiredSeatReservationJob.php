<?php

namespace App\Jobs;

use App\Models\SeatReservation;
use App\Services\SeatReservationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReleaseExpiredSeatReservationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $reservationId) {}

    public function handle(SeatReservationService $seatReservationService): void
    {
        $reservation = SeatReservation::query()->find($this->reservationId);
        if (! $reservation || $reservation->status !== SeatReservation::STATUS_RESERVED) {
            return;
        }

        if ($reservation->booking_id) {
            $seatReservationService->releaseForBooking((int) $reservation->booking_id);
        } elseif ($reservation->trip_id) {
            $seatReservationService->restoreForTrip((int) $reservation->trip_id);
        }
    }
}
