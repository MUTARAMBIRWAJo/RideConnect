<?php

namespace App\Listeners;

use App\Events\Domain\BusPositionUpdated;
use App\Events\Domain\BusRouteAssignmentCreated;
use App\Events\Domain\DriverAvailabilityChanged;
use App\Events\Domain\PassengerBoardingUpdated;
use App\Events\Domain\PaymentVerified;
use App\Events\Domain\SeatAvailabilityChanged;
use App\Events\Domain\StopArrivalReported;
use App\Events\Domain\TicketIssued;
use App\Events\Domain\TripAssignmentCreated;
use App\Events\Domain\TripAssignmentRejected;
use App\Events\Domain\TripReassigned;
use App\Models\BusPositionUpdate;
use App\Models\BusRouteAssignment;
use App\Models\PassengerBoardingEvent;
use App\Models\Payment;
use App\Models\StopArrivalEvent;
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
            $event instanceof BusRouteAssignmentCreated => $this->busRouteAssignmentCreated($event),
            $event instanceof BusPositionUpdated => $this->busPositionUpdated($event),
            $event instanceof StopArrivalReported => $this->stopArrivalReported($event),
            $event instanceof PassengerBoardingUpdated => $this->passengerBoardingUpdated($event),
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

    private function busRouteAssignmentCreated(BusRouteAssignmentCreated $event): void
    {
        $assignment = BusRouteAssignment::query()->with(['bus', 'driver.user', 'corridor'])->find($event->assignmentId);
        if (! $assignment) {
            return;
        }

        $payload = $this->payload([
            'assignment_id' => $assignment->id,
            'corridor_id' => $assignment->corridor_id,
            'bus_id' => $assignment->bus_id,
            'driver_id' => $assignment->driver_id,
            'status' => $assignment->status,
        ], $assignment->active_trip_id);

        $this->realtimeGateway->broadcast("corridor:{$assignment->corridor_id}", 'bus.assignment.created', $payload);
        $this->realtimeGateway->broadcast("bus:{$assignment->bus_id}", 'bus.assignment.created', $payload);
        if ($assignment->driver_id) {
            $this->realtimeGateway->broadcast("driver:{$assignment->driver_id}", 'bus.assignment.created', $payload);
        }
    }

    private function busPositionUpdated(BusPositionUpdated $event): void
    {
        $update = BusPositionUpdate::query()->with(['assignment.bus', 'assignment.driver.user', 'nextStop'])->find($event->updateId);
        if (! $update) {
            return;
        }

        $payload = $this->payload([
            'update_id' => $update->id,
            'assignment_id' => $update->bus_route_assignment_id,
            'bus_id' => $update->assignment?->bus_id,
            'latitude' => (float) $update->latitude,
            'longitude' => (float) $update->longitude,
            'speed_kph' => (float) ($update->speed_kph ?? 0),
            'heading_degrees' => $update->heading_degrees,
            'next_stop' => $update->nextStop?->only(['id', 'stop_name', 'stop_order']),
            'eta_minutes' => $update->eta_minutes,
            'route_progress_percent' => (float) ($update->route_progress_percent ?? 0),
        ], $update->trip_id);

        if ($update->assignment?->corridor_id) {
            $this->realtimeGateway->broadcast("corridor:{$update->assignment->corridor_id}", 'bus.position.updated', $payload);
        }
        if ($update->assignment?->bus_id) {
            $this->realtimeGateway->broadcast("bus:{$update->assignment->bus_id}", 'bus.position.updated', $payload);
        }
        if ($update->trip_id) {
            $this->realtimeGateway->broadcast("trip:{$update->trip_id}", 'bus.position.updated', $payload);
        }
    }

    private function stopArrivalReported(StopArrivalReported $event): void
    {
        $arrival = StopArrivalEvent::query()->with(['assignment.bus', 'assignment.driver.user', 'stop'])->find($event->eventId);
        if (! $arrival) {
            return;
        }

        $payload = $this->payload([
            'event_id' => $arrival->id,
            'assignment_id' => $arrival->bus_route_assignment_id,
            'corridor_stop_id' => $arrival->corridor_stop_id,
            'stop' => $arrival->stop?->only(['id', 'stop_name', 'stop_order']),
            'arrival_time' => $arrival->arrival_time?->toIso8601String(),
            'departure_time' => $arrival->departure_time?->toIso8601String(),
            'is_terminal' => (bool) $arrival->is_terminal,
        ], $arrival->trip_id);

        if ($arrival->assignment?->corridor_id) {
            $this->realtimeGateway->broadcast("corridor:{$arrival->assignment->corridor_id}", 'bus.stop.arrived', $payload);
        }
        if ($arrival->trip_id) {
            $this->realtimeGateway->broadcast("trip:{$arrival->trip_id}", 'bus.stop.arrived', $payload);
        }
    }

    private function passengerBoardingUpdated(PassengerBoardingUpdated $event): void
    {
        $boardingEvent = PassengerBoardingEvent::query()->with(['boarding.trip', 'boarding.corridor', 'boarding.boardingStop', 'boarding.destinationStop'])->find($event->eventId);
        if (! $boardingEvent) {
            return;
        }

        $boarding = $boardingEvent->boarding;
        $payload = $this->payload([
            'event_id' => $boardingEvent->id,
            'boarding_id' => $boarding?->id,
            'ticket_code' => $boarding?->ticket_code,
            'status' => $boardingEvent->status,
            'passenger_id' => $boardingEvent->passenger_id,
            'boarding_stop' => $boarding?->boardingStop?->only(['id', 'stop_name', 'stop_order']),
            'destination_stop' => $boarding?->destinationStop?->only(['id', 'stop_name', 'stop_order']),
        ], $boardingEvent->trip_id);

        if ($boardingEvent->passenger_id) {
            $this->realtimeGateway->broadcast("passenger:{$boardingEvent->passenger_id}", 'bus.passenger.boarding.updated', $payload);
        }
        if ($boardingEvent->trip_id) {
            $this->realtimeGateway->broadcast("trip:{$boardingEvent->trip_id}", 'bus.passenger.boarding.updated', $payload);
        }
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
