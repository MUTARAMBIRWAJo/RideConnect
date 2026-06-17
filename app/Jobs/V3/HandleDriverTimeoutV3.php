<?php

namespace App\Jobs\V3;

use App\Models\V3\TripV3;
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
        if ($freshTrip->status === 'DRIVER_OFFERED' && $freshTrip->matched_driver_id === $this->driverId) {
            
            // Mark as rejected via timeout
            $ignored = $freshTrip->ignored_driver_ids ?? [];
            if (!in_array($this->driverId, $ignored)) {
                $ignored[] = $this->driverId;
            }
            
            $freshTrip->ignored_driver_ids = $ignored;
            $freshTrip->matched_driver_id = null;
            $freshTrip->driver_response_status = 'rejected'; // timed out counts as rejected
            // transition back to searching
            app(\App\Services\V3\TripLifecycleEngineV3::class)->transition($freshTrip, 'SEARCHING');
            
            // Restart matching
            ProcessTripMatchingV3::dispatch($freshTrip);
        }
    }
}
