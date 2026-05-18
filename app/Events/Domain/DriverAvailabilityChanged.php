<?php

namespace App\Events\Domain;

class DriverAvailabilityChanged
{
    public function __construct(public readonly int $driverId, public readonly string $status, public readonly ?int $tripId = null) {}
}
