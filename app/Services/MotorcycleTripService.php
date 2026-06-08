<?php

namespace App\Services;

use App\Jobs\RetryTripMatchingJob;
use App\Models\Driver;
use App\Models\MotorcycleTrip;
use App\Models\Notification;
use App\Services\Matching\RadiusExpansionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MotorcycleTripService
{
    private MatchingService $matchingService;
    private NotificationService $notificationService;

    public function __construct(
        MatchingService $matchingService,
        NotificationService $notificationService
    ) {
        $this->matchingService = $matchingService;
        $this->notificationService = $notificationService;
    }

    /**
     * Create a motorcycle trip request
     */
    public function createTrip(
        int $passengerId,
        string $pickupLocation,
        string $dropoffLocation,
        float $pickupLat,
        float $pickupLng,
        float $dropoffLat,
        float $dropoffLng,
        float $estimatedFare
    ): array {
        try {
            $trip = MotorcycleTrip::create([
                'passenger_id' => $passengerId,
                'pickup_location' => $pickupLocation,
                'pickup_lat' => $pickupLat,
                'pickup_lng' => $pickupLng,
                'dropoff_location' => $dropoffLocation,
                'dropoff_lat' => $dropoffLat,
                'dropoff_lng' => $dropoffLng,
                'estimated_fare' => $estimatedFare,
                'currency' => 'RWF',
                'status' => 'REQUESTED',
                'requested_at' => now(),
            ]);

            // Notify passenger
            $this->notificationService->sendInAppNotification(
                $passengerId,
                'TRIP_REQUESTED',
                'Trip Request Created',
                'Your motorcycle trip request has been created',
                ['trip_id' => $trip->id]
            );

            Log::info('Motorcycle trip created', [
                'trip_id' => $trip->id,
                'passenger_id' => $passengerId,
            ]);

            return [
                'success' => true,
                'trip_id' => $trip->id,
                'status' => $trip->status,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create motorcycle trip', [
                'passenger_id' => $passengerId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'CREATION_FAILED',
                'message' => 'Failed to create trip request',
            ];
        }
    }

    /**
     * Start matching process for a trip
     */
    public function startMatching(MotorcycleTrip $trip): array
    {
        try {
            $trip->update([
                'status' => 'MATCHING',
                'matching_status' => 'SEARCHING',
                'matching_started_at' => now(),
                'current_search_radius_km' => $trip->initial_search_radius_km ?? 5,
                'retry_count' => 0,
            ]);

            Log::info('Starting trip matching', [
                'trip_id' => $trip->id,
                'initial_search_radius_km' => $trip->current_search_radius_km,
            ]);

            // Fast local match on the critical path (no ML/route HTTP — never hangs
            // on a cold ML dyno). In debug mode, assign the nearest driver anywhere.
            $debug = config('matching.mode') === 'debug';
            $initialRadius = $debug
                ? RadiusExpansionService::MAX_RADIUS_KM
                : (float) ($trip->initial_search_radius_km ?? 5);

            $match = config('matching.fast_match', true) || $debug
                ? $this->matchingService->fastLocalMatch($trip, [], $initialRadius)
                : $this->matchingService->matchMotorcycleTrip($trip);

            if ($match) {
                // Driver found on first attempt
                return $this->assignDriver($trip, $match);
            }

            // No driver found - use retry system instead of immediate expiration
            Log::warning('No drivers found on initial match attempt, scheduling retries', [
                'trip_id' => $trip->id,
            ]);

            // Update trip to MATCHING_PENDING state
            $trip->update([
                'status' => 'MATCHING_PENDING',
                'matching_status' => 'RETRY_SCHEDULED',
                'retry_count' => 0,
                'max_retries' => 5,
            ]);

            // Schedule first retry after 15 seconds
            dispatch(new RetryTripMatchingJob($trip->id))->delay(now()->addSeconds(15));

            Log::info('First retry scheduled for trip', [
                'trip_id' => $trip->id,
                'retry_delay_seconds' => 15,
            ]);

            // Notify passenger that we're still looking
            $this->notificationService->sendInAppNotification(
                $trip->passenger_id,
                'TRIP_MATCHING',
                'Finding a driver...',
                'We\'re searching for an available driver. Please wait.',
                ['trip_id' => $trip->id]
            );

            return [
                'success' => true,
                'trip_id' => $trip->id,
                'status' => $trip->status,
                'matching_status' => 'RETRY_SCHEDULED',
                'message' => 'Finding a driver...',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to start matching', [
                'trip_id' => $trip->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'MATCHING_FAILED',
                'message' => 'Matching failed',
            ];
        }
    }

    /**
     * Assign driver to trip (extracted for reuse)
     */
    /**
     * Attempt a single fast match for a still-searching trip. Safe to call
     * repeatedly (e.g. from the status poll) — this is what drives matching to
     * completion without depending on a queue worker. Returns a small result
     * describing the attempt; persists candidate count + radius for the API.
     *
     * @return array{success:bool,driver_id?:int,candidate_count:int,radius_km:float}
     */
    public function attemptMatch(MotorcycleTrip $trip, float $radiusKm, bool $debug = false): array
    {
        // Only pre-assignment trips are matchable.
        if (! in_array($trip->status, ['REQUESTED', 'MATCHING', 'MATCHING_PENDING'], true) || $trip->driver_id) {
            return ['success' => false, 'candidate_count' => 0, 'radius_km' => $radiusKm];
        }

        $radius = $debug ? RadiusExpansionService::MAX_RADIUS_KM : $radiusKm;
        $excluded = $this->rejectedDriverIds($trip);

        $match = $this->matchingService->fastLocalMatch($trip, $excluded, $radius);
        $candidateCount = $match['candidate_count'] ?? 0;

        // Record progress so the status endpoint can report radius/candidates.
        $trip->forceFill([
            'current_search_radius_km' => $radius,
            'metadata' => array_merge($trip->metadata ?? [], [
                'last_candidate_count' => $candidateCount,
                'last_search_radius_km' => $radius,
            ]),
        ])->save();

        if ($match && ! empty($match['driver_id'])) {
            $this->assignDriver($trip, $match);
            return ['success' => true, 'driver_id' => $match['driver_id'], 'candidate_count' => $candidateCount, 'radius_km' => $radius];
        }

        // Keep the trip in a searchable state for the next poll/retry.
        $trip->forceFill([
            'status' => 'MATCHING_PENDING',
            'matching_status' => 'SEARCHING',
        ])->save();

        return ['success' => false, 'candidate_count' => $candidateCount, 'radius_km' => $radius];
    }

    /**
     * Normalize rejected_drivers (cast to array, but tolerate legacy JSON strings).
     */
    private function rejectedDriverIds(MotorcycleTrip $trip): array
    {
        $rejected = $trip->rejected_drivers;
        if (is_array($rejected)) {
            return $rejected;
        }
        if (is_string($rejected) && $rejected !== '') {
            $decoded = json_decode($rejected, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    private function assignDriver(MotorcycleTrip $trip, array $match): array
    {
        // Assign driver
        $trip->update([
            'driver_id' => $match['driver_id'],
            'status' => 'ASSIGNED',
            'matching_status' => 'DRIVER_FOUND',
            'assigned_at' => now(),
        ]);

        // Mark driver unavailable
        $driver = Driver::find($match['driver_id']);
        if ($driver) {
            $driver->update([
                'is_available' => false,
                'current_trip_id' => $trip->id,
                'availability_status' => 'busy',
            ]);
        }

        // Notify driver
        $this->notificationService->sendInAppNotification(
            $driver->user_id,
            'TRIP_ASSIGNED',
            'New Motorcycle Trip Assigned',
            "{$trip->pickup_location} → {$trip->dropoff_location}",
            [
                'trip_id' => $trip->id,
                'fare' => $trip->estimated_fare,
                'pickup_lat' => $trip->pickup_lat,
                'pickup_lng' => $trip->pickup_lng,
            ]
        );

        // Notify passenger
        $this->notificationService->sendInAppNotification(
            $trip->passenger_id,
            'TRIP_ASSIGNED',
            'Driver Found',
            'A driver has been assigned',
            ['trip_id' => $trip->id]
        );

        Log::info('Motorcycle trip matched and assigned', [
            'trip_id' => $trip->id,
            'driver_id' => $match['driver_id'],
        ]);

        return [
            'success' => true,
            'trip_id' => $trip->id,
            'driver_id' => $match['driver_id'],
            'status' => $trip->status,
        ];
    }

    /**
     * Driver accepts trip
     */
    public function acceptTrip(MotorcycleTrip $trip, int $driverId): array
    {
        try {
            // Validate
            if ($trip->driver_id !== $driverId) {
                return [
                    'success' => false,
                    'error' => 'NOT_ASSIGNED_TO_DRIVER',
                    'message' => 'Trip not assigned to this driver',
                ];
            }

            if ($trip->status !== 'ASSIGNED') {
                return [
                    'success' => false,
                    'error' => 'INVALID_STATUS',
                    'message' => 'Trip cannot be accepted in current status',
                ];
            }

            // Update trip
            $trip->update([
                'status' => 'DRIVER_ASSIGNED',
                'accepted_at' => now(),
            ]);

            // Get driver with user
            $driver = Driver::with('user')->find($driverId);

            // Notify passenger
            $this->notificationService->sendInAppNotification(
                $trip->passenger_id,
                'TRIP_ACCEPTED',
                'Driver Accepted',
                'Your driver has accepted the trip',
                [
                    'trip_id' => $trip->id,
                    'driver_name' => $driver->user->name,
                    'driver_phone' => $driver->user->phone,
                    'vehicle_plate' => $driver->motorcycle_plate ?? 'N/A',
                    'estimated_arrival' => 5, // minutes
                ]
            );

            // Notify driver
            $this->notificationService->sendInAppNotification(
                $driver->user_id,
                'TRIP_ACCEPTED',
                'Trip Accepted',
                'Trip accepted successfully. Head to pickup location.',
                ['trip_id' => $trip->id]
            );

            Log::info('Motorcycle trip accepted', [
                'trip_id' => $trip->id,
                'driver_id' => $driverId,
            ]);

            return [
                'success' => true,
                'trip_id' => $trip->id,
                'status' => $trip->status,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to accept trip', [
                'trip_id' => $trip->id,
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'ACCEPT_FAILED',
                'message' => 'Failed to accept trip',
            ];
        }
    }

    /**
     * Driver rejects trip
     */
    public function rejectTrip(MotorcycleTrip $trip, int $driverId, string $reason): array
    {
        try {
            // Validate
            if ($trip->driver_id !== $driverId) {
                return [
                    'success' => false,
                    'error' => 'NOT_ASSIGNED_TO_DRIVER',
                    'message' => 'Trip not assigned to this driver',
                ];
            }

            // Update trip
            $trip->update([
                'status' => 'REJECTED_BY_DRIVER',
                'rejected_driver_id' => $driverId,
                'rejection_reason' => $reason,
                'rejected_at' => now(),
                'rejected_drivers' => array_merge($trip->rejected_drivers ?? [], [$driverId]),
            ]);

            // Mark driver available again
            $driver = Driver::find($driverId);
            if ($driver) {
                $driver->update([
                    'is_available' => true,
                    'current_trip_id' => null,
                    'availability_status' => 'available',
                ]);
            }

            // Notify passenger
            $this->notificationService->sendInAppNotification(
                $trip->passenger_id,
                'TRIP_REJECTED',
                'Driver Unavailable',
                'Finding another driver...',
                ['trip_id' => $trip->id]
            );

            Log::info('Motorcycle trip rejected', [
                'trip_id' => $trip->id,
                'driver_id' => $driverId,
                'reason' => $reason,
            ]);

            // Attempt rematching
            $excludeDrivers = $trip->rejected_drivers ?? [$driverId];
            $rematchSuccess = $this->matchingService->rematchTrip($trip, $excludeDrivers);

            if ($rematchSuccess) {
                $newDriver = $trip->fresh()->driver;
                $this->notificationService->sendInAppNotification(
                    $newDriver->user_id,
                    'TRIP_ASSIGNED',
                    'New Motorcycle Trip Assigned',
                    "{$trip->pickup_location} → {$trip->dropoff_location}",
                    ['trip_id' => $trip->id]
                );
            }

            return [
                'success' => true,
                'trip_id' => $trip->id,
                'status' => $trip->fresh()->status,
                'rematched' => $rematchSuccess,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to reject trip', [
                'trip_id' => $trip->id,
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'REJECT_FAILED',
                'message' => 'Failed to reject trip',
            ];
        }
    }

    /**
     * Driver arrived at pickup
     */
    public function driverArrived(MotorcycleTrip $trip, int $driverId): array
    {
        try {
            if ($trip->driver_id !== $driverId || $trip->status !== 'DRIVER_ASSIGNED') {
                return [
                    'success' => false,
                    'error' => 'INVALID_STATE',
                    'message' => 'Cannot mark driver arrived in current state',
                ];
            }

            $trip->update([
                'status' => 'PASSENGER_WAITING',
                'driver_arrived_at' => now(),
            ]);

            // Notify passenger
            $this->notificationService->sendInAppNotification(
                $trip->passenger_id,
                'DRIVER_ARRIVED',
                'Driver Arrived',
                'Your driver has arrived. Please go to the pickup location.',
                ['trip_id' => $trip->id]
            );

            Log::info('Driver arrived at pickup', [
                'trip_id' => $trip->id,
                'driver_id' => $driverId,
            ]);

            return [
                'success' => true,
                'trip_id' => $trip->id,
                'status' => $trip->status,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to mark driver arrived', [
                'trip_id' => $trip->id,
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'ARRIVED_FAILED',
                'message' => 'Failed to update arrival status',
            ];
        }
    }

    /**
     * Start trip
     */
    public function startTrip(MotorcycleTrip $trip, int $driverId): array
    {
        try {
            if ($trip->driver_id !== $driverId || $trip->status !== 'PASSENGER_WAITING') {
                return [
                    'success' => false,
                    'error' => 'INVALID_STATE',
                    'message' => 'Cannot start trip in current state',
                ];
            }

            $trip->update([
                'status' => 'IN_PROGRESS',
                'started_at' => now(),
            ]);

            // Notify passenger
            $this->notificationService->sendInAppNotification(
                $trip->passenger_id,
                'TRIP_STARTED',
                'Trip Started',
                'Your trip has started',
                ['trip_id' => $trip->id]
            );

            Log::info('Trip started', [
                'trip_id' => $trip->id,
                'driver_id' => $driverId,
            ]);

            return [
                'success' => true,
                'trip_id' => $trip->id,
                'status' => $trip->status,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to start trip', [
                'trip_id' => $trip->id,
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'START_FAILED',
                'message' => 'Failed to start trip',
            ];
        }
    }

    /**
     * Complete trip
     */
    public function completeTrip(MotorcycleTrip $trip, int $driverId, float $actualFare = null): array
    {
        try {
            if ($trip->driver_id !== $driverId || $trip->status !== 'IN_PROGRESS') {
                return [
                    'success' => false,
                    'error' => 'INVALID_STATE',
                    'message' => 'Cannot complete trip in current state',
                ];
            }

            // Update trip
            $trip->update([
                'status' => 'COMPLETED',
                'completed_at' => now(),
                'actual_fare' => $actualFare ?? $trip->estimated_fare,
            ]);

            // Mark driver available
            $driver = Driver::find($driverId);
            if ($driver) {
                $driver->update([
                    'is_available' => true,
                    'current_trip_id' => null,
                    'availability_status' => 'available',
                ]);
            }

            // Notify passenger
            $this->notificationService->sendInAppNotification(
                $trip->passenger_id,
                'TRIP_COMPLETED',
                'Trip Completed',
                'Your trip has been completed successfully',
                [
                    'trip_id' => $trip->id,
                    'fare' => $trip->actual_fare ?? $trip->estimated_fare,
                ]
            );

            // Notify driver
            $this->notificationService->sendInAppNotification(
                $driver->user_id,
                'TRIP_COMPLETED',
                'Trip Completed',
                'Trip completed successfully',
                ['trip_id' => $trip->id]
            );

            Log::info('Trip completed', [
                'trip_id' => $trip->id,
                'driver_id' => $driverId,
                'fare' => $trip->actual_fare ?? $trip->estimated_fare,
            ]);

            return [
                'success' => true,
                'trip_id' => $trip->id,
                'status' => $trip->status,
                'fare' => $trip->actual_fare ?? $trip->estimated_fare,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to complete trip', [
                'trip_id' => $trip->id,
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'COMPLETE_FAILED',
                'message' => 'Failed to complete trip',
            ];
        }
    }

    /**
     * Passenger cancels trip
     */
    public function cancelByPassenger(MotorcycleTrip $trip, int $passengerId, string $reason = null): array
    {
        try {
            if ($trip->passenger_id !== $passengerId) {
                return [
                    'success' => false,
                    'error' => 'NOT_PASSENGER',
                    'message' => 'Only passenger can cancel',
                ];
            }

            if (in_array($trip->status, ['COMPLETED', 'CANCELLED_BY_PASSENGER', 'CANCELLED_BY_DRIVER'])) {
                return [
                    'success' => false,
                    'error' => 'CANNOT_CANCEL',
                    'message' => 'Cannot cancel trip in current status',
                ];
            }

            // Update trip
            $trip->update([
                'status' => 'CANCELLED_BY_PASSENGER',
                'cancelled_at' => now(),
                'notes' => $reason,
            ]);

            // Mark driver available
            if ($trip->driver_id) {
                $driver = Driver::find($trip->driver_id);
                if ($driver) {
                    $driver->update([
                        'is_available' => true,
                        'current_trip_id' => null,
                        'availability_status' => 'available',
                    ]);
                }

                // Notify driver
                $this->notificationService->sendInAppNotification(
                    $driver->user_id,
                    'TRIP_CANCELLED',
                    'Trip Cancelled',
                    'Trip was cancelled by passenger',
                    ['trip_id' => $trip->id]
                );
            }

            Log::info('Trip cancelled by passenger', [
                'trip_id' => $trip->id,
                'passenger_id' => $passengerId,
            ]);

            return [
                'success' => true,
                'trip_id' => $trip->id,
                'status' => $trip->status,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to cancel trip', [
                'trip_id' => $trip->id,
                'passenger_id' => $passengerId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'CANCEL_FAILED',
                'message' => 'Failed to cancel trip',
            ];
        }
    }
}
