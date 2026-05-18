<?php

namespace App\Events\Domain;

class TripAssignmentCreated
{
    public function __construct(public readonly int $attemptId) {}
}
