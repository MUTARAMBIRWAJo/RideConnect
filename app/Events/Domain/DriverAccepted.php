<?php

namespace App\Events\Domain;

class DriverAccepted
{
    public function __construct(
        public readonly int $tripId,
        public readonly int $driverId,
    ) {}
}
