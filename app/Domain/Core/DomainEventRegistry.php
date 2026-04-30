<?php

namespace App\Domain\Core;

use App\Events\Domain\BookingCreated;
use App\Events\Domain\RideCreated;
use App\Events\Domain\TripCompleted;
use App\Events\Domain\TripMatched;
use App\Events\Domain\TripStarted;
use App\Listeners\Domain\BookingCreatedListener;
use App\Listeners\Domain\RideCreatedListener;
use App\Listeners\Domain\TripCompletedListener;
use App\Listeners\Domain\TripMatchedListener;
use App\Listeners\Domain\TripStartedListener;

class DomainEventRegistry
{
    public static function listeners(): array
    {
        return [
            RideCreated::class => [RideCreatedListener::class],
            BookingCreated::class => [BookingCreatedListener::class],
            TripMatched::class => [TripMatchedListener::class],
            TripStarted::class => [TripStartedListener::class],
            TripCompleted::class => [TripCompletedListener::class],
        ];
    }
}
