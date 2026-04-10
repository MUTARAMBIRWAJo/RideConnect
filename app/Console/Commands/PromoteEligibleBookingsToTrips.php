<?php

namespace App\Console\Commands;

use App\Models\Ride;
use App\Services\RideCategoryTransitionService;
use Illuminate\Console\Command;

class PromoteEligibleBookingsToTrips extends Command
{
    protected $signature = 'rides:promote-bookings-to-trips {--ride_id= : Optionally limit promotion to one ride ID}';

    protected $description = 'Synchronize booking/trip categories based on departure time threshold';

    public function handle(RideCategoryTransitionService $transitionService): int
    {
        $rideId = $this->option('ride_id');
        $ride = null;

        if ($rideId !== null) {
            $ride = Ride::find((int) $rideId);

            if (! $ride) {
                $this->error("Ride with ID {$rideId} was not found.");

                return self::FAILURE;
            }
        }

        $result = $transitionService->synchronizeTravelCategories($ride);

        $scope = $ride ? "ride {$ride->id}" : 'all rides';
        $this->info("Synchronized travel categories for {$scope}: promoted {$result['promoted']} booking(s) to trip(s), demoted {$result['demoted']} trip(s) to booking(s).");

        return self::SUCCESS;
    }
}
