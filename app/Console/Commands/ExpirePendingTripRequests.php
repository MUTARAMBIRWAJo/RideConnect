<?php

namespace App\Console\Commands;

use App\Domain\Trip\TripStateMachine;
use App\Models\Trip;
use App\Services\DriverAssignmentService;
use App\Services\MobileNotificationService;
use Illuminate\Console\Command;

class ExpirePendingTripRequests extends Command
{
    protected $signature = 'trips:expire-pending-requests {--minutes=3 : Acceptance window in minutes before auto-cancel}';

    protected $description = 'Auto-cancel pending trip requests that were not accepted in time';

    public function handle(
        MobileNotificationService $mobileNotificationService,
        DriverAssignmentService $driverAssignmentService,
    ): int {
        $minutes = max(1, (int) $this->option('minutes'));
        $cutoff = now()->subMinutes($minutes);
        $expiredCount = 0;
        $reassignedCount = 0;

        Trip::query()
            ->whereIn('status', ['PENDING', TripStateMachine::REQUESTED])
            ->whereNotNull('requested_at')
            ->where('requested_at', '<=', $cutoff)
            ->orderBy('requested_at')
            ->chunkById(100, function ($trips) use (&$expiredCount, &$reassignedCount, $mobileNotificationService, $driverAssignmentService) {
                foreach ($trips as $trip) {
                    $trip->loadMissing('ride', 'driver');

                    if ($trip->ride) {
                        $nextTrip = $driverAssignmentService->reassignToNextDriver(
                            $trip,
                            $trip->ride,
                            $trip->driver_id ? [(int) $trip->driver_id] : []
                        );

                        if ($nextTrip) {
                            $nextDriver = $nextTrip->driver;

                            if ($nextDriver) {
                                $mobileNotificationService->sendRideRequestToDriver($nextTrip->fresh(['driver.user']), $nextDriver);
                                $mobileNotificationService->sendTripReassignedToPassenger($nextTrip->fresh(['driver.user']), $nextDriver);
                            }

                            $reassignedCount++;

                            continue;
                        }
                    }

                    $updated = Trip::query()
                        ->whereKey($trip->id)
                        ->whereIn('status', ['PENDING', TripStateMachine::REQUESTED])
                        ->update([
                            'status' => TripStateMachine::CANCELLED,
                            'rejection_reason' => 'acceptance_timeout',
                            'rejected_at' => now(),
                        ]);

                    if ($trip->driver_id) {
                        \App\Models\Driver::query()
                            ->whereKey($trip->driver_id)
                            ->update(['availability_status' => 'available']);
                    }

                    if ($updated !== 1) {
                        continue;
                    }

                    $expiredCount++;
                    $expiredTrip = $trip->fresh();

                    if (! $expiredTrip) {
                        continue;
                    }

                    $mobileNotificationService->sendTripAcceptanceTimedOutToPassenger($expiredTrip);
                    $mobileNotificationService->sendTripAcceptanceTimedOutToDriver($expiredTrip);
                }
            });

        $this->info("Cancelled {$expiredCount} stale trip request(s); reassigned {$reassignedCount} trip request(s) to the next match.");

        return self::SUCCESS;
    }
}
