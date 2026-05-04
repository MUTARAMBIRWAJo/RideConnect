<?php

namespace App\Domain\Core;

use App\Events\Domain\BookingCreated;
use App\Events\Domain\RideCreated;
use App\Events\Domain\TripCompleted;
use App\Events\Domain\TripMatched;
use App\Events\Domain\TripStarted;
use App\Listeners\Domain\BookingCreatedListener;
use App\Listeners\Domain\RideCreatedListener;
use App\Listeners\BroadcastTripEvents;

class DomainEventRegistry
{
    public static function listeners(): array
    {
        return [
            RideCreated::class => [RideCreatedListener::class],
            BookingCreated::class => [BookingCreatedListener::class],
            TripMatched::class => [BroadcastTripEvents::class],
            TripStarted::class => [BroadcastTripEvents::class],
            TripCompleted::class => [BroadcastTripEvents::class],
        ];
    }
}
