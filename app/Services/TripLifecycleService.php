<?php

namespace App\Services;

use App\Events\TripAcceptedByDriver;
use App\Events\TripAssignedToDriver;
use App\Events\TripReassignedToNewDriver;
use App\Models\TripRequest;
use App\Models\Vehicle;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing the complete trip lifecycle.
 * Handles: acceptance, rejection, reassignment, seat management, and notifications.
 */
class TripLifecycleService
{
    private NotificationService $notificationService;

    public function __construct()
    {
        $this->notificationService = app(NotificationService::class);
    }

    /**
     * Driver accepts a trip request.
     */
    public function acceptTrip(TripRequest $trip, int $driverId): array
    {
        try {
            // Validate trip is assigned
            if (! in_array($trip->status, ['BUS_ASSIGNED', 'PENDING_MATCH'], true)) {
                return [
                    'success' => false,
                    'message' => "Trip cannot be accepted in {$trip->status} status",
                    'error_code' => 'INVALID_STATUS',
                ];
            }

            // Validate driver is assigned
            if ($trip->matched_driver_id && $trip->matched_driver_id !== $driverId) {
                return [
                    'success' => false,
                    'message' => 'You are not assigned to this trip',
                    'error_code' => 'NOT_ASSIGNED',
                ];
            }

            // Check if this is a public bus trip
            if ($trip->matched_vehicle_id) {
                $bus = Vehicle::find($trip->matched_vehicle_id);
                if ($bus && $bus->seats > 0) {
                    Log::info('Bus capacity validated for trip request', [
                        'bus_id' => $bus->id,
                        'capacity' => $bus->seats,
                        'trip_id' => $trip->id,
                    ]);
                } elseif ($bus) {
                    return [
                        'success' => false,
                        'message' => 'No seats available on this bus',
                        'error_code' => 'SEAT_UNAVAILABLE',
                    ];
                }
            }

            // Update trip status
            $trip->update([
                'status' => 'PASSENGER_WAITING',
                'matched_driver_id' => $driverId,
            ]);

            // Get driver and vehicle info for passenger notification
            $driver = $trip->driver;
            $vehicle = $trip->vehicle;

            $driverName = $driver?->user?->name ?? 'Driver';
            $vehicleInfo = $vehicle
                ? trim(sprintf('%s %s %s', $vehicle->year, $vehicle->make, $vehicle->model))
                : 'Vehicle';
            $vehicleInfo = $vehicleInfo !== '' ? $vehicleInfo : 'Vehicle';
            $etaMinutes = (int) ($trip->trip_duration_minutes ?? 0);

            // Broadcast event for real-time updates
            event(new TripAcceptedByDriver(
                $trip,
                $trip->passenger_id,
                [
                    'id' => $driver?->id,
                    'name' => $driverName,
                    'phone' => $driver?->user?->phone,
                ],
                [
                    'id' => $vehicle?->id,
                    'name' => $vehicleInfo,
                    'available_seats' => $vehicle?->seats ?? 0,
                ],
                $etaMinutes
            ));

            // Send notifications
            $this->notificationService->sendInAppNotification(
                $trip->passenger_id,
                'TRIP_ACCEPTED',
                'Driver Accepted Your Trip',
                "Driver $driverName is on the way - ETA $etaMinutes mins",
                ['trip_id' => $trip->id, 'eta_minutes' => $etaMinutes]
            );

            Log::info('Trip accepted successfully', [
                'trip_id' => $trip->id,
                'driver_id' => $driverId,
                'status' => 'PASSENGER_WAITING',
            ]);

            return [
                'success' => true,
                'message' => 'Trip accepted successfully',
                'data' => [
                    'trip_id' => $trip->id,
                    'status' => $trip->status,
                    'driver' => [
                        'id' => $driver?->id,
                        'name' => $driverName,
                    ],
                    'vehicle' => [
                        'id' => $vehicle?->id,
                        'registration' => $vehicleInfo,
                        'available_seats' => $vehicle?->seats ?? 0,
                    ],
                    'eta_minutes' => $etaMinutes,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error accepting trip', [
                'trip_id' => $trip->id,
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while accepting the trip',
                'error_code' => 'ACCEPT_ERROR',
            ];
        }
    }

    /**
     * Driver rejects a trip - triggers reassignment.
     */
    public function rejectTrip(TripRequest $trip, int $driverId, string $reason = 'DRIVER_DECLINED'): array
    {
        try {
            // Validate driver is assigned
            if ($trip->matched_driver_id !== $driverId) {
                return [
                    'success' => false,
                    'message' => 'You are not assigned to this trip',
                    'error_code' => 'NOT_ASSIGNED',
                ];
            }

            // Cannot reject if already in progress
            if (in_array($trip->status, ['IN_TRANSIT', 'COMPLETED', 'CANCELLED'])) {
                return [
                    'success' => false,
                    'message' => "Cannot reject trip in {$trip->status} status",
                    'error_code' => 'CANNOT_REJECT',
                ];
            }

            // Update trip status
            $trip->update([
                'status' => 'REJECTED_BY_DRIVER',
                'matched_driver_id' => null,
            ]);

            // Notify passenger of rejection
            $this->notificationService->sendInAppNotification(
                $trip->passenger_id,
                'TRIP_REJECTED',
                'Driver Assignment Changed',
                'Finding a new driver...',
                ['trip_id' => $trip->id, 'reason' => $reason]
            );

            Log::info('Trip rejected by driver', [
                'trip_id' => $trip->id,
                'driver_id' => $driverId,
                'reason' => $reason,
            ]);

            // Trigger reassignment
            $reassignmentResult = $this->reassignTrip($trip);

            return [
                'success' => true,
                'message' => 'Trip rejected. Reassigning to new driver...',
                'data' => [
                    'trip_id' => $trip->id,
                    'status' => $trip->status,
                    'reason' => $reason,
                    'reassignment' => $reassignmentResult,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error rejecting trip', [
                'trip_id' => $trip->id,
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while rejecting the trip',
                'error_code' => 'REJECT_ERROR',
            ];
        }
    }

    /**
     * Reassign trip to a new driver via ML service.
     */
    public function reassignTrip(TripRequest $trip): array
    {
        try {
            $mlServiceUrl = config('services.ml_service.url') ?? 'https://ml-service-j72g.onrender.com';

            $payload = [
                'trip_request_id' => $trip->id,
                'pickup_lat' => (float) $trip->pickup_lat,
                'pickup_lng' => (float) $trip->pickup_lng,
                'vehicle_type' => 'PUBLIC_BUS',
            ];

            Log::info('Calling ML service for reassignment', [
                'trip_id' => $trip->id,
                'url' => "$mlServiceUrl/reassign",
            ]);

            $response = Http::timeout(30)
                ->retry(2, 100)
                ->post("$mlServiceUrl/reassign", $payload);

            if (!$response->successful()) {
                Log::warning('ML service reassignment failed', [
                    'trip_id' => $trip->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to find new driver',
                ];
            }

            $data = $response->json();
            $newDriverId = $data['assigned_driver_id'] ?? null;
            $newVehicleId = $data['vehicle_id'] ?? null;

            if (!$newDriverId) {
                Log::warning('ML service did not return driver', [
                    'trip_id' => $trip->id,
                    'response' => $data,
                ]);

                return [
                    'success' => false,
                    'message' => 'No drivers available',
                ];
            }

            // Update trip with new assignment
            $oldDriverId = $trip->matched_driver_id;
            $trip->update([
                'status' => 'BUS_ASSIGNED',
                'matched_driver_id' => $newDriverId,
                'matched_vehicle_id' => $newVehicleId,
            ]);

            // Broadcast reassignment event
            event(new TripReassignedToNewDriver(
                $trip,
                $oldDriverId ?? 0,
                $newDriverId,
                $trip->passenger_id
            ));

            // Send notifications
            $this->notificationService->sendInAppNotification(
                $newDriverId,
                'TRIP_ASSIGNED',
                'New Trip Assigned',
                "{$trip->pickup_location} → {$trip->dropoff_location}",
                ['trip_id' => $trip->id]
            );

            Log::info('Trip reassigned successfully', [
                'trip_id' => $trip->id,
                'old_driver_id' => $oldDriverId,
                'new_driver_id' => $newDriverId,
            ]);

            return [
                'success' => true,
                'new_driver_id' => $newDriverId,
            ];
        } catch (ConnectionException $e) {
            Log::error('Failed to connect to ML service', [
                'trip_id' => $trip->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Connection error with matching service',
            ];
        } catch (\Exception $e) {
            Log::error('Error reassigning trip', [
                'trip_id' => $trip->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred during reassignment',
            ];
        }
    }

    /**
     * Assign a trip to a driver initially.
     */
    public function assignTrip(TripRequest $trip, int $driverId, int $vehicleId): bool
    {
        try {
            $trip->update([
                'status' => 'BUS_ASSIGNED',
                'matched_driver_id' => $driverId,
                'matched_vehicle_id' => $vehicleId,
            ]);

            // Send notification to driver
            $this->notificationService->sendInAppNotification(
                $driverId,
                'TRIP_ASSIGNED',
                'New Trip Request',
                "{$trip->pickup_location} → {$trip->dropoff_location}",
                ['trip_id' => $trip->id, 'fare' => $trip->estimated_fare]
            );

            // Broadcast event
            event(new TripAssignedToDriver(
                $trip,
                $driverId,
                $trip->pickup_location,
                $trip->dropoff_location,
                (float) $trip->estimated_fare
            ));

            Log::info('Trip assigned to driver', [
                'trip_id' => $trip->id,
                'driver_id' => $driverId,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error assigning trip', [
                'trip_id' => $trip->id,
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
