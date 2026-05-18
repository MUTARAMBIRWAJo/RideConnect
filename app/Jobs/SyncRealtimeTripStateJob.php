<?php

namespace App\Jobs;

use App\Models\Trip;
use App\Services\Realtime\RealtimeGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class SyncRealtimeTripStateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $tripId) {}

    public function handle(RealtimeGateway $realtimeGateway): void
    {
        $trip = Trip::query()->with(['driver', 'payment', 'transportTicket'])->find($this->tripId);
        if (! $trip) {
            return;
        }

        $payload = [
            'event_id' => (string) Str::uuid(),
            'trip_id' => $trip->id,
            'status' => $trip->status,
            'assignment_status' => $trip->assignment_status,
            'payment_status' => $trip->payment_status,
            'driver_id' => $trip->driver_id,
            'ticket_id' => $trip->transportTicket?->id,
            'updated_at' => now()->toIso8601String(),
            'version' => now()->getTimestampMs(),
        ];

        $realtimeGateway->broadcastTripUpdate($trip->id, $payload);
        if ($trip->passenger_id) {
            $realtimeGateway->notifyPassenger((int) $trip->passenger_id, $payload);
        }
        if ($trip->driver_id) {
            $realtimeGateway->notifyDriver((int) $trip->driver_id, $payload);
        }
    }
}
