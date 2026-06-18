<?php

namespace App\Services\V3;

use App\Jobs\V3\HandleDriverTimeoutV3;
use App\Jobs\V3\ProcessTripMatchingV3;
use App\Models\V3\TripV3;
use Illuminate\Support\Facades\Log;

class TripMatchingEngineV3
{
    private TripLifecycleEngineV3 $lifecycle;
    private DriverAvailabilityServiceV3 $availabilityService;
    private NotificationServiceV3 $notificationService;

    public function __construct(
        TripLifecycleEngineV3 $lifecycle,
        DriverAvailabilityServiceV3 $availabilityService,
        NotificationServiceV3 $notificationService
    ) {
        $this->lifecycle = $lifecycle;
        $this->availabilityService = $availabilityService;
        $this->notificationService = $notificationService;
    }

    public function startMatching(TripV3 $trip): void
    {
        $this->lifecycle->transition($trip, 'MATCHING');
        ProcessTripMatchingV3::dispatch($trip);
    }

    public function executeMatch(TripV3 $trip): void
    {
        // Limit max attempts to 5-10
        if ($trip->match_attempt_count >= 10) {
            $this->lifecycle->cancel($trip, 'NO_DRIVER_AVAILABLE');
            $this->notificationService->sendToPassenger($trip->user_id, [
                'type' => 'TRIP_REJECTED',
                'message' => 'No drivers available at the moment. Please try again.',
            ]);
            return;
        }

        $ignoredIds = $trip->ignored_driver_ids ?? [];
        $radiusKm = 5.0; // Dynamic radius based on attempt count or transport type could be added

        $metadata = is_string($trip->metadata) ? json_decode($trip->metadata, true) : ($trip->metadata ?? []);
        
        if (!empty($metadata['driver_id'])) {
            $selectedDriver = \App\Models\Driver::find($metadata['driver_id']);
            // Unset driver_id so we don't infinitely retry the same driver if they reject
            $metadata['driver_id'] = null;
            $trip->metadata = $metadata;
            $trip->save();
        } else {
            $availableDrivers = $this->availabilityService->getNearbyAvailableDrivers(
                $trip->pickup_lat ?? -1.95, // Fallback coordinates if null
                $trip->pickup_lng ?? 30.06,
                $radiusKm,
                $trip->transport_type,
                $ignoredIds
            );
            $selectedDriver = $availableDrivers->first();
        }

        if (!$selectedDriver) {
            // No drivers found in this pass. Wait and retry or cancel.
            // For now, cancel to prevent infinite loop
            $this->lifecycle->cancel($trip, 'NO_DRIVER_AVAILABLE');
            $this->notificationService->sendToPassenger($trip->user_id, [
                'type' => 'TRIP_REJECTED',
                'message' => 'No drivers available at the moment. Please try again.',
            ]);
            return;
        }

        // Assign driver
        $trip->matched_driver_id = $selectedDriver->id;
        $trip->driver_response_status = 'pending';
        $trip->match_attempt_count += 1;
        $trip->last_matched_at = now();
        $this->lifecycle->transition($trip, 'DRIVER_FOUND');

        // Notify Driver
        $this->notificationService->sendToDriver($selectedDriver->id, [
            'type' => 'NEW_TRIP_REQUEST',
            'trip_id' => $trip->id,
            'pickup' => $trip->pickup_location,
            'dropoff' => $trip->dropoff_location,
            'fare' => $trip->fare_estimate ?? 4500,
            'message' => 'New trip request available. Accept or reject.',
        ]);

        // Dispatch timeout handler
        HandleDriverTimeoutV3::dispatch($trip, $selectedDriver->id)->delay(now()->addSeconds(30));
    }
}
