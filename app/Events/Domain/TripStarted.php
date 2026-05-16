<?php

namespace App\Events\Domain;

class TripStarted
{
    public function __construct(
        public readonly int $tripId,
    ) {}
}
