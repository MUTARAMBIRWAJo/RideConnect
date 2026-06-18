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
        if (is_null($trip->matching_started_at)) {
            $trip->matching_started_at = now();
            $trip->save();
        }
        $this->lifecycle->transition($trip, 'MATCHING');
        ProcessTripMatchingV3::dispatch($trip);
    }

    public function executeMatch(TripV3 $trip): void
    {
        // Limit max attempts to 5-10
        if ($trip->match_attempt_count >= 10) {
            $trip->matching_timeout_at = now();
            $trip->save();
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
        
        $startedAt = $trip->matching_started_at;
        if (is_string($startedAt)) {
            $startedAt = \Carbon\Carbon::parse($startedAt);
        }
        $elapsedSeconds = $startedAt ? $startedAt->diffInSeconds(now()) : 0;
        $isFallback = $elapsedSeconds > 60;

        if (!empty($metadata['driver_id'])) {
            $selectedDriver = \App\Models\Driver::find($metadata['driver_id']);
            $metadata['driver_id'] = null;
            $trip->metadata = $metadata;
            $trip->save();
        } elseif ($isFallback) {
            $trip->fallback_match_used = true;
            $trip->save();

            // Stage 2: Fallback Match - Find nearest driver ignoring strict ML and radius rules
            $lat = $trip->pickup_lat ?? -1.95;
            $lng = $trip->pickup_lng ?? 30.06;
            $haversine = "( 6371 * acos( cos( radians($lat) ) * cos( radians( current_latitude ) ) * cos( radians( current_longitude ) - radians($lng) ) + sin( radians($lat) ) * sin( radians( current_latitude ) ) ) )";
            
            $query = \App\Models\Driver::query()
                ->select('drivers.*')
                ->where('drivers.status', 'approved')
                ->where('drivers.is_online', true)
                ->whereIn('drivers.availability_status', ['online', 'available'])
                ->whereNull('drivers.current_trip_id');

            if (!empty($ignoredIds)) {
                $query->whereNotIn('drivers.id', $ignoredIds);
            }

            $query->join('vehicles', 'vehicles.driver_id', '=', 'drivers.id');
            if ($trip->transport_type === 'motor_vehicle') {
                $query->whereIn('vehicles.vehicle_type', ['motorcycle', 'boda', 'moto', 'motorbike', 'tuk-tuk']);
            } elseif ($trip->transport_type === 'public_bus') {
                $query->whereIn('vehicles.vehicle_type', ['bus', 'BUS', 'minibus', 'coach']);
            } elseif ($trip->transport_type === 'private_car') {
                $query->whereIn('vehicles.vehicle_type', ['sedan', 'suv', 'hatchback', 'van', 'compact', 'minivan']);
            }

            $selectedDriver = $query->orderByRaw("$haversine ASC")->first();
            
            // Stage 3: Absolute Fallback
            // If the specific vehicle type fallback failed, just find ANY online driver (moto or car), EXCEPT buses.
            if (!$selectedDriver && $trip->transport_type !== 'public_bus') {
                $queryAny = \App\Models\Driver::query()
                    ->select('drivers.*')
                    ->where('drivers.status', 'approved')
                    ->where('drivers.is_online', true)
                    ->whereIn('drivers.availability_status', ['online', 'available'])
                    ->whereNull('drivers.current_trip_id')
                    ->join('vehicles', 'vehicles.driver_id', '=', 'drivers.id')
                    ->whereNotIn('vehicles.vehicle_type', ['bus', 'BUS', 'minibus', 'coach']);

                if (!empty($ignoredIds)) {
                    $queryAny->whereNotIn('drivers.id', $ignoredIds);
                }

                $selectedDriver = $queryAny->orderByRaw("$haversine ASC")->first();
            }
        } else {
            // Stage 1: Intelligent Match
            $availableDrivers = $this->availabilityService->getNearbyAvailableDrivers(
                $trip->pickup_lat ?? -1.95,
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
            $trip->matching_timeout_at = now();
            $trip->save();
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
        $trip->matched_at = now();
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
