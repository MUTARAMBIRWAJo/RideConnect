<?php

namespace App\Jobs\V3;

use App\Models\V3\TripV3;
use App\Models\DriverTripOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class HandleDriverTimeoutV3 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public TripV3 $trip;
    public int $driverId;

    public function __construct(TripV3 $trip, int $driverId)
    {
        $this->trip = $trip;
        $this->driverId = $driverId;
    }

    public function handle(): void
    {
        // Re-fetch trip fresh from DB
        $freshTrip = TripV3::find($this->trip->id);

        if (!$freshTrip) {
            return;
        }

        // Check if trip is still offered to this driver and they haven't responded
        if ($freshTrip->status === 'MATCHING' && $freshTrip->matched_driver_id === $this->driverId && $freshTrip->driver_response_status === 'pending') {
            
            // Mark as rejected via timeout
            $ignored = $freshTrip->ignored_driver_ids ?? [];
            if (!in_array($this->driverId, $ignored)) {
                $ignored[] = $this->driverId;
            }
            
            $freshTrip->ignored_driver_ids = $ignored;
            $freshTrip->matched_driver_id = null;
            $freshTrip->driver_response_status = 'timeout';
            $freshTrip->save();

            DriverTripOffer::query()
                ->where('trip_id', $freshTrip->id)
                ->where('driver_id', $this->driverId)
                ->where('status', 'pending')
                ->update([
                    'status' => 'timeout',
                    'responded_at' => now(),
                    'response_reason' => 'Driver did not respond within 30 seconds.',
                    'updated_at' => now(),
                ]);

            app(\App\Services\V3\TripLifecycleNotifierV3::class)->dispatch($freshTrip, 'trip.driver.rejected', [
                'trip_id' => $freshTrip->id,
                'driver_id' => $this->driverId,
                'reason' => 'timeout',
                'message' => 'Driver offer timed out. Finding another driver...',
            ]);
            
            // Restart matching
            ProcessTripMatchingV3::dispatch($freshTrip);
        }
    }
}
