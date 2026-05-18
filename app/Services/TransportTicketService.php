<?php

namespace App\Services;

use App\Events\Domain\TicketIssued;
use App\Models\TransportTicket;
use App\Models\Trip;
use Illuminate\Support\Str;

class TransportTicketService
{
    public function issueForTrip(Trip $trip): TransportTicket
    {
        $trip->loadMissing(['booking', 'ride', 'payment']);

        $ticket = TransportTicket::query()->firstOrNew(['trip_id' => $trip->id]);
        if ($ticket->exists && $ticket->status !== TransportTicket::STATUS_CANCELLED) {
            return $ticket;
        }

        $ticketCode = $this->ticketCode($trip->id);
        $payload = $this->qrPayload($trip, $ticketCode);

        $ticket->fill([
            'ticket_code' => $ticketCode,
            'qr_payload' => $payload,
            'ride_id' => $trip->ride_id,
            'booking_id' => $trip->booking_id,
            'passenger_id' => $trip->passenger_id,
            'driver_id' => $trip->driver_id,
            'seat_count' => (int) ($trip->booking?->seats_booked ?? 1),
            'payment_status' => $trip->payment_status ?? $trip->payment?->status ?? 'pending',
            'status' => TransportTicket::STATUS_ISSUED,
            'issued_at' => now(),
        ])->save();

        event(new TicketIssued($ticket->id));

        return $ticket->fresh();
    }

    public function validateTicket(int $ticketId): TransportTicket
    {
        $ticket = TransportTicket::query()->findOrFail($ticketId);
        $ticket->update([
            'status' => TransportTicket::STATUS_VALIDATED,
            'validated_at' => now(),
        ]);

        return $ticket->fresh();
    }

    private function ticketCode(int $tripId): string
    {
        return sprintf('RC-%s-%d-%s', now()->format('Ymd'), $tripId, strtoupper(Str::random(6)));
    }

    private function qrPayload(Trip $trip, string $ticketCode): array
    {
        $payload = [
            'ticket_code' => $ticketCode,
            'trip_id' => $trip->id,
            'passenger_id' => $trip->passenger_id,
            'payment_status' => $trip->payment_status ?? 'pending',
            'validation_timestamp' => now()->toIso8601String(),
        ];

        $payload['signature'] = hash_hmac('sha256', json_encode($payload), (string) config('app.key'));

        return $payload;
    }
}
