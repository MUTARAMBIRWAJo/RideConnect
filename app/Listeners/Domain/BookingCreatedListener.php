<?php

namespace App\Listeners\Domain;

use App\Events\Domain\BookingCreated;
use App\Services\Realtime\RealtimeGateway;
use Illuminate\Support\Facades\Log;
use Throwable;

class BookingCreatedListener
{
    public function __construct(private readonly RealtimeGateway $realtimeGateway) {}

    public function handle(BookingCreated $event): void
    {
        try {
            $this->realtimeGateway->broadcastTripUpdate($event->bookingId, [
                'type' => 'booking_created',
                'booking_id' => $event->bookingId,
                'ride_id' => $event->rideId,
                'user_id' => $event->userId,
            ]);

            Log::info('Booking created event received', [
                'booking_id' => $event->bookingId,
                'ride_id' => $event->rideId,
            ]);
        } catch (Throwable $throwable) {
            Log::error('BookingCreatedListener failed', [
                'booking_id' => $event->bookingId,
                'error' => $throwable->getMessage(),
            ]);
        }
    }
}
