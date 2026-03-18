<?php

namespace App\Console\Commands;

use App\Models\Ride;
use App\Services\RideCategoryTransitionService;
use Illuminate\Console\Command;

class PromoteEligibleBookingsToTrips extends Command
{
    protected $signature = 'rides:promote-bookings-to-trips {--ride_id= : Optionally limit promotion to one ride ID}';

    protected $description = 'Promote bookings into trips when departure is within or equal to 6 hours';

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

        $converted = $transitionService->promoteEligibleBookingsToTrips($ride);

        $scope = $ride ? "ride {$ride->id}" : 'all rides';
        $this->info("Promoted {$converted} booking(s) to trip(s) for {$scope}.");

        return self::SUCCESS;
    }
}
