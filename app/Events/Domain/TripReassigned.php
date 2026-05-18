<?php

namespace App\Events\Domain;

class TripReassigned
{
    public function __construct(public readonly int $tripId, public readonly ?int $oldDriverId, public readonly ?int $newDriverId) {}
}
