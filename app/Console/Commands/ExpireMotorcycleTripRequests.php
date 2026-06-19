<?php

namespace App\Console\Commands;

use App\Models\MotorcycleTrip;
use App\Models\Driver;
use App\Services\MatchingService;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireMotorcycleTripRequests extends Command
{
    protected $signature = 'motorcycle-trips:expire-pending {--seconds=180 : Acceptance window in seconds before auto-reassign}';
    protected $description = 'Auto-reassign motorcycle trip requests that were not accepted in time';

    public function handle(
        MatchingService $matchingService,
        NotificationService $notificationService,
        \App\Services\MotorcycleTripService $motorcycleTripService
    ): int {
        $seconds = max(10, (int) $this->option('seconds'));
        $cutoff = now()->subSeconds($seconds);
        $reassignedCount = 0;
        $failedCount = 0;

        $trips = MotorcycleTrip::query()
            ->where('status', 'ASSIGNED')
            ->whereNotNull('assigned_at')
            ->where('assigned_at', '<=', $cutoff)
            ->get();

        foreach ($trips as $trip) {
            Log::info('Motorcycle trip driver response timeout', [
                'trip_id' => $trip->id,
                'driver_id' => $trip->driver_id,
            ]);

            // Add driver to excluded list
            $excluded = $trip->rejected_drivers ? json_decode($trip->rejected_drivers, true) : [];
            if ($trip->driver_id && !in_array($trip->driver_id, $excluded)) {
                $excluded[] = $trip->driver_id;
                $trip->rejected_drivers = json_encode($excluded);
            }

            // Free up the driver
            if ($trip->driver_id) {
                Driver::where('id', $trip->driver_id)->update([
                    'is_available' => true,
                    'current_trip_id' => null,
                    'availability_status' => 'available',
                ]);
            }

            // Reset trip to MATCHING_PENDING to allow rematch
            $trip->update([
                'status' => 'MATCHING_PENDING',
                'matching_status' => 'REMATCHING',
                'driver_id' => null,
                'assigned_at' => null,
            ]);

            // Attempt rematch
            $match = $matchingService->matchMotorcycleTrip($trip, $excluded);

            if ($match && !empty($match['driver_id'])) {
                $motorcycleTripService->assignDriver($trip, $match);
                $reassignedCount++;
            } else {
                $failedCount++;
                // If rematch failed, it stays in MATCHING_PENDING and the next poll or background job will pick it up
            }
        }

        $this->info("Reassigned {$reassignedCount} stale motorcycle trip request(s); {$failedCount} failed to rematch immediately.");
        return self::SUCCESS;
    }
}
