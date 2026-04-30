<?php

namespace App\Events\Domain;

class RideCreated
{
    public function __construct(
        public readonly int $rideId,
        public readonly int $driverId,
    ) {
    }
}
