<?php

namespace App\Domain\Core;

use App\Events\Domain\BookingCreated;
use App\Events\Domain\BusPositionUpdated;
use App\Events\Domain\BusRouteAssignmentCreated;
use App\Events\Domain\DriverAvailabilityChanged;
use App\Events\Domain\PassengerBoardingUpdated;
use App\Events\Domain\PaymentVerified;
use App\Events\Domain\RideCreated;
use App\Events\Domain\SeatAvailabilityChanged;
use App\Events\Domain\TicketIssued;
use App\Events\Domain\TripAssignmentCreated;
use App\Events\Domain\TripAssignmentRejected;
use App\Events\Domain\TripCompleted;
use App\Events\Domain\TripMatched;
use App\Events\Domain\TripReassigned;
use App\Events\Domain\TripStarted;
use App\Listeners\BroadcastPublicTransportEvents;
use App\Listeners\BroadcastTripEvents;
use App\Listeners\Domain\BookingCreatedListener;
use App\Listeners\Domain\RideCreatedListener;
use App\Listeners\Firebase\UnifiedFirebaseSyncListener;

class DomainEventRegistry
{
    public static function listeners(): array
    {
        return [
            RideCreated::class => [RideCreatedListener::class],
            BookingCreated::class => [BookingCreatedListener::class],
            TripMatched::class => [BroadcastTripEvents::class, UnifiedFirebaseSyncListener::class],
            TripStarted::class => [BroadcastTripEvents::class, UnifiedFirebaseSyncListener::class],
            TripCompleted::class => [BroadcastTripEvents::class, UnifiedFirebaseSyncListener::class],
            DriverAvailabilityChanged::class => [BroadcastPublicTransportEvents::class],
            BusRouteAssignmentCreated::class => [BroadcastPublicTransportEvents::class],
            BusPositionUpdated::class => [BroadcastPublicTransportEvents::class],
            PassengerBoardingUpdated::class => [BroadcastPublicTransportEvents::class],
            SeatAvailabilityChanged::class => [BroadcastPublicTransportEvents::class],
            TripAssignmentCreated::class => [BroadcastPublicTransportEvents::class],
            TripAssignmentRejected::class => [BroadcastPublicTransportEvents::class],
            TripReassigned::class => [BroadcastPublicTransportEvents::class],
            PaymentVerified::class => [UnifiedFirebaseSyncListener::class],
            TicketIssued::class => [BroadcastPublicTransportEvents::class],
        ];
    }
}
