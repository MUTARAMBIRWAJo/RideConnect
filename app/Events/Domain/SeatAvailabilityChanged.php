<?php

namespace App\Events\Domain;

class SeatAvailabilityChanged
{
    public function __construct(public readonly int $rideId, public readonly int $availableSeats, public readonly ?int $tripId = null) {}
}
