<?php

namespace App\Events\Domain;

class BookingCreated
{
    public function __construct(
        public readonly int $bookingId,
        public readonly int $rideId,
        public readonly int $userId,
    ) {
    }
}
