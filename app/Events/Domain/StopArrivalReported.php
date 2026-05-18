<?php

namespace App\Events\Domain;

class StopArrivalReported
{
    public function __construct(public readonly int $eventId) {}
}