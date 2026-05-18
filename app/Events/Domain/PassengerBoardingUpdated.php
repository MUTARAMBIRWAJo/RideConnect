<?php

namespace App\Events\Domain;

class PassengerBoardingUpdated
{
    public function __construct(public readonly int $eventId) {}
}