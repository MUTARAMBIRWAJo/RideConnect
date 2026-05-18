<?php

namespace App\Events\Domain;

class BusRouteAssignmentCreated
{
    public function __construct(public readonly int $assignmentId) {}
}