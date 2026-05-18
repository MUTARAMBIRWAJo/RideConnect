<?php

namespace App\Listeners;

use App\Events\Domain\DriverAvailabilityChanged;
use App\Events\Domain\PaymentVerified;
use App\Events\Domain\SeatAvailabilityChanged;
use App\Events\Domain\TicketIssued;
use App\Events\Domain\TripAssignmentCreated;
use App\Events\Domain\TripAssignmentRejected;
use App\Events\Domain\TripReassigned;
use App\Models\Payment;
use App\Models\TransportTicket;
use App\Models\TripAssignmentAttempt;
use App\Services\Realtime\RealtimeGateway;
use Illuminate\Support\Str;

class BroadcastPublicTransportEvents
{
    public function __construct(private readonly RealtimeGateway $realtimeGateway) {}

    public function handle(object $event): void
    {
        match (true) {
            $event instanceof DriverAvailabilityChanged => $this->driverAvailability($event),
            $event instanceof SeatAvailabilityChanged => $this->seatAvailability($event),
            $event instanceof TripAssignmentCreated => $this->assignmentCreated($event),
            $event instanceof TripAssignmentRejected => $this->assignmentRejected($event),
            $event instanceof TripReassigned => $this->tripReassigned($event),
            $event instanceof PaymentVerified => $this->paymentVerified($event),
            $event instanceof TicketIssued => $this->ticketIssued($event),
            default => null,
        };
    }

    private function driverAvailability(DriverAvailabilityChanged $event): void
    {
        $this->realtimeGateway->broadcast("driver:{$event->driverId}", 'driver.availability.changed', $this->payload([
            'driver_id' => $event->driverId,
            'status' => $event->status,
        ], $event->tripId));
    }

    private function seatAvailability(SeatAvailabilityChanged $event): void
    {
        $this->realtimeGateway->broadcast("ride:{$event->rideId}", 'seat.availability.changed', $this->payload([
            'ride_id' => $event->rideId,
            'available_seats' => $event->availableSeats,
        ], $event->tripId));
    }

    private function assignmentCreated(TripAssignmentCreated $event): void
    {
        $attempt = TripAssignmentAttempt::query()->with('trip')->find($event->attemptId);
        if (! $attempt) {
            return;
        }

        $payload = $this->payload([
            'attempt_id' => $attempt->id,
            'driver_id' => $attempt->driver_id,
            'score' => $attempt->score,
            'score_breakdown' => $attempt->score_breakdown,
            'expires_at' => $attempt->expires_at?->toIso8601String(),
        ], $attempt->trip_id);

        $this->realtimeGateway->broadcast("driver:{$attempt->driver_id}", 'trip.assignment.created', $payload);
        if ($attempt->trip?->passenger_id) {
            $this->realtimeGateway->broadcast("passenger:{$attempt->trip->passenger_id}", 'trip.assignment.created', $payload);
        }
    }

    private function assignmentRejected(TripAssignmentRejected $event): void
    {
        $attempt = TripAssignmentAttempt::query()->with('trip')->find($event->attemptId);
        if (! $attempt) {
            return;
        }

        $payload = $this->payload([
            'attempt_id' => $attempt->id,
            'driver_id' => $attempt->driver_id,
            'reason' => $attempt->rejection_reason,
        ], $attempt->trip_id);

        if ($attempt->trip?->passenger_id) {
            $this->realtimeGateway->broadcast("passenger:{$attempt->trip->passenger_id}", 'trip.assignment.rejected', $payload);
        }
    }

    private function tripReassigned(TripReassigned $event): void
    {
        $this->realtimeGateway->broadcast("trip:{$event->tripId}", 'trip.reassigned', $this->payload([
            'old_driver_id' => $event->oldDriverId,
            'new_driver_id' => $event->newDriverId,
        ], $event->tripId));
    }

    private function paymentVerified(PaymentVerified $event): void
    {
        $payment = Payment::query()->with('trip')->find($event->paymentId);
        $payload = $this->payload([
            'payment_id' => $event->paymentId,
            'status' => $payment?->status ?? 'paid',
        ], $event->tripId);

        if ($event->tripId) {
            $this->realtimeGateway->broadcast("trip:{$event->tripId}", 'payment.verified', $payload);
        }
        if ($payment?->trip?->passenger_id) {
            $this->realtimeGateway->broadcast("passenger:{$payment->trip->passenger_id}", 'payment.verified', $payload);
        }
    }

    private function ticketIssued(TicketIssued $event): void
    {
        $ticket = TransportTicket::query()->find($event->ticketId);
        if (! $ticket) {
            return;
        }

        $payload = $this->payload([
            'ticket_id' => $ticket->id,
            'ticket_code' => $ticket->ticket_code,
            'status' => $ticket->status,
        ], $ticket->trip_id);

        $this->realtimeGateway->broadcast("trip:{$ticket->trip_id}", 'ticket.issued', $payload);
        if ($ticket->passenger_id) {
            $this->realtimeGateway->broadcast("passenger:{$ticket->passenger_id}", 'ticket.issued', $payload);
        }
    }

    private function payload(array $data, ?int $tripId): array
    {
        return array_merge([
            'event_id' => (string) Str::uuid(),
            'trip_id' => $tripId,
            'updated_at' => now()->toIso8601String(),
            'version' => now()->getTimestampMs(),
        ], $data);
    }
}
