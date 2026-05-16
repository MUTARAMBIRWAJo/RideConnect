<?php

namespace App\Events\Domain;

class TripCompleted
{
    public function __construct(
        public readonly int $tripId,
    ) {}
}
