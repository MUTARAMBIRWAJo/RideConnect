<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Driver;
use App\Models\Notification;
use App\Models\Trip;
use App\Models\User;

class MobileNotificationService
{
    public function __construct(private readonly PushDeliveryBridge $pushDeliveryBridge) {}

    public function sendRideRequestToDriver(Trip $trip, Driver $driver): ?Notification
    {
        $driverUserId = $driver->user_id;
        if (! $driverUserId) {
            return null;
        }

        return $this->sendToUserId(
            $driverUserId,
            'ride_request_received',
            'New Ride Request',
            'You have a new ride request. Please accept or reject.',
            [
                'trip_id' => $trip->id,
                'pickup_location' => $trip->pickup_location,
                'dropoff_location' => $trip->dropoff_location,
                'fare' => (float) $trip->fare,
                'actions' => [
                    'accept' => "/api/v1/driver/requests/{$trip->id}/accept",
                    'reject' => "/api/v1/driver/requests/{$trip->id}/reject",
                ],
            ]
        );
    }

    public function sendRideAcceptedToPassenger(Trip $trip, ?Driver $driver = null): ?Notification
    {
        $passengerUserId = $this->resolvePassengerUserId($trip);
        if (! $passengerUserId) {
            return null;
        }

        return $this->sendToUserId(
            $passengerUserId,
            'ride_request_accepted',
            'Ride Request Accepted',
            'Your ride request has been accepted by a driver.',
            [
                'trip_id' => $trip->id,
                'driver_id' => $driver?->id ?? $trip->driver_id,
                'accepted_at' => $trip->accepted_at?->toIso8601String(),
                'status' => $trip->status,
            ]
        );
    }

    public function sendRideRejectedToPassenger(Trip $trip, ?Driver $driver = null, ?string $reason = null): ?Notification
    {
        $passengerUserId = $this->resolvePassengerUserId($trip);
        if (! $passengerUserId) {
            return null;
        }

        return $this->sendToUserId(
            $passengerUserId,
            'ride_request_rejected',
            'Ride Request Rejected',
            'Your ride request was rejected by the driver. Please choose another driver.',
            [
                'trip_id' => $trip->id,
                'driver_id' => $driver?->id,
                'reason' => $reason,
                'status' => $trip->status,
            ]
        );
    }

    public function sendTripStartedToPassenger(Trip $trip): ?Notification
    {
        $passengerUserId = $this->resolvePassengerUserId($trip);
        if (! $passengerUserId) {
            return null;
        }

        return $this->sendToUserId(
            $passengerUserId,
            'trip_started',
            'Trip Started',
            'Your trip has started.',
            [
                'trip_id' => $trip->id,
                'status' => $trip->status,
                'started_at' => $trip->started_at?->toIso8601String(),
            ]
        );
    }

    public function sendTripCompletedToPassenger(Trip $trip): ?Notification
    {
        $passengerUserId = $this->resolvePassengerUserId($trip);
        if (! $passengerUserId) {
            return null;
        }

        return $this->sendToUserId(
            $passengerUserId,
            'trip_completed',
            'Trip Completed',
            'Your trip has been completed.',
            [
                'trip_id' => $trip->id,
                'status' => $trip->status,
                'completed_at' => $trip->completed_at?->toIso8601String(),
            ]
        );
    }

    public function sendTripCancelledToPassenger(Trip $trip, ?string $reason = null): ?Notification
    {
        $passengerUserId = $this->resolvePassengerUserId($trip);
        if (! $passengerUserId) {
            return null;
        }

        return $this->sendToUserId(
            $passengerUserId,
            'trip_cancelled',
            'Trip Cancelled',
            'Your trip was cancelled.',
            [
                'trip_id' => $trip->id,
                'status' => $trip->status,
                'reason' => $reason,
            ]
        );
    }

    public function sendTripCancelledToDriver(Trip $trip, ?string $reason = null): ?Notification
    {
        $driverUserId = Driver::query()->where('id', $trip->driver_id)->value('user_id');
        if (! $driverUserId) {
            return null;
        }

        return $this->sendToUserId(
            (int) $driverUserId,
            'trip_cancelled',
            'Trip Cancelled',
            'A trip assigned to you was cancelled.',
            [
                'trip_id' => $trip->id,
                'status' => $trip->status,
                'reason' => $reason,
            ]
        );
    }

    public function sendBookingRequestToDriver(Booking $booking): ?Notification
    {
        $driverUserId = $booking->ride?->driver?->user_id;
        if (! $driverUserId) {
            return null;
        }

        return $this->sendToUserId(
            (int) $driverUserId,
            'booking_request_received',
            'New Booking Request',
            'A passenger requested seats on your ride.',
            [
                'booking_id' => $booking->id,
                'ride_id' => $booking->ride_id,
                'seats_booked' => (int) $booking->seats_booked,
                'total_price' => (float) $booking->total_price,
                'status' => $booking->status,
            ]
        );
    }

    public function sendBookingConfirmedToPassenger(Booking $booking): ?Notification
    {
        return $this->sendToUserId(
            (int) $booking->user_id,
            'booking_confirmed',
            'Booking Confirmed',
            'Your booking has been confirmed by the driver.',
            [
                'booking_id' => $booking->id,
                'ride_id' => $booking->ride_id,
                'status' => $booking->status,
                'confirmed_at' => $booking->confirmed_at?->toIso8601String(),
            ]
        );
    }

    public function sendBookingCancelledToPassenger(Booking $booking, string $actor): ?Notification
    {
        return $this->sendToUserId(
            (int) $booking->user_id,
            'booking_cancelled',
            'Booking Cancelled',
            'Your booking was cancelled.',
            [
                'booking_id' => $booking->id,
                'ride_id' => $booking->ride_id,
                'status' => $booking->status,
                'cancelled_at' => $booking->cancelled_at?->toIso8601String(),
                'cancellation_reason' => $booking->cancellation_reason,
                'cancelled_by' => $actor,
            ]
        );
    }

    public function sendBookingCancelledToDriver(Booking $booking, string $actor): ?Notification
    {
        $driverUserId = $booking->ride?->driver?->user_id;
        if (! $driverUserId) {
            return null;
        }

        return $this->sendToUserId(
            (int) $driverUserId,
            'booking_cancelled',
            'Booking Cancelled',
            'A booking on your ride was cancelled.',
            [
                'booking_id' => $booking->id,
                'ride_id' => $booking->ride_id,
                'status' => $booking->status,
                'cancelled_at' => $booking->cancelled_at?->toIso8601String(),
                'cancellation_reason' => $booking->cancellation_reason,
                'cancelled_by' => $actor,
            ]
        );
    }

    public function sendToUserId(int $userId, string $type, string $title, string $message, array $data = []): Notification
    {
        $notification = Notification::query()->create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'is_read' => false,
        ]);

        $user = User::query()->find($userId);
        if ($user) {
            $this->pushDeliveryBridge->deliverUserNotification($user, $notification);
        }

        return $notification;
    }

    private function resolvePassengerUserId(Trip $trip): ?int
    {
        return User::query()->where('mobile_user_id', $trip->passenger_id)->value('id');
    }
}
