<?php

namespace App\Services;

use App\Domain\Trip\TripStateMachine;
use App\Events\Domain\TripCompleted;
use App\Exceptions\DomainException;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\TripStatusEvent;
use Illuminate\Support\Facades\DB;

class TripCompletionService
{
    public function __construct(
        private readonly SeatReservationService $seatReservationService,
        private readonly TransportTicketService $ticketService,
    ) {}

    public function complete(int $tripId, ?int $actorId = null, bool $adminApproved = false, ?string $adminReason = null): Trip
    {
        return DB::transaction(function () use ($tripId, $actorId, $adminApproved, $adminReason): Trip {
            $trip = Trip::query()->with(['payment', 'booking.payment', 'driver'])->lockForUpdate()->findOrFail($tripId);
            $oldStatus = (string) $trip->status;

            TripStateMachine::assertTransitionForTrip($trip, TripStateMachine::COMPLETED);

            $payment = $trip->payment ?: $trip->booking?->payment;
            $paymentPaid = $payment && in_array(strtolower((string) $payment->status), ['paid', 'completed'], true);
            if (! $paymentPaid && ! $adminApproved) {
                throw DomainException::make(
                    'Payment must be verified before completion',
                    'PAYMENT_NOT_VERIFIED'
                );
            }

            $trip->update([
                'status' => TripStateMachine::COMPLETED,
                'payment_status' => $paymentPaid ? 'paid' : ($trip->payment_status ?? 'pending'),
                'completed_at' => now(),
                'admin_completed_by' => $adminApproved ? $actorId : null,
                'admin_completion_reason' => $adminApproved ? $adminReason : null,
            ]);

            $this->seatReservationService->consumeForTrip($trip->id);
            $this->ticketService->issueForTrip($trip->fresh());

            if ($trip->driver_id) {
                Driver::query()->whereKey($trip->driver_id)->update(['availability_status' => 'available']);
            }

            TripStatusEvent::query()->create([
                'trip_id' => $trip->id,
                'actor_type' => $adminApproved ? 'admin' : 'driver',
                'actor_id' => $actorId,
                'old_status' => $oldStatus,
                'new_status' => TripStateMachine::COMPLETED,
                'metadata' => [
                    'admin_approved' => $adminApproved,
                    'payment_id' => $payment?->id,
                    'payment_status' => $payment?->status,
                ],
                'created_at' => now(),
            ]);

            event(new TripCompleted((int) $trip->id));

            return $trip->fresh();
        });
    }
}
