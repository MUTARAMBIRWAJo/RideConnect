<?php

namespace App\Events\Domain;

class TripMatched
{
    public function __construct(
        public readonly int $tripId,
        public readonly int $driverId,
    ) {}
}
