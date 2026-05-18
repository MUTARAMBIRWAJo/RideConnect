<?php

namespace App\Events\Domain;

class TripAssignmentRejected
{
    public function __construct(public readonly int $attemptId) {}
}
