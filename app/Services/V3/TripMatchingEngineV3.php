<?php

namespace App\Services\V3;

use App\Jobs\V3\HandleDriverTimeoutV3;
use App\Jobs\V3\ProcessTripMatchingV3;
use App\Models\Driver;
use App\Models\DriverTripOffer;
use App\Models\V3\TripV3;
use Illuminate\Support\Facades\Log;

class TripMatchingEngineV3
{
    private TripLifecycleEngineV3 $lifecycle;
    private DriverAvailabilityServiceV3 $availabilityService;
    private NotificationServiceV3 $notificationService;
    private TripLifecycleNotifierV3 $notifier;

    public function __construct(
        TripLifecycleEngineV3 $lifecycle,
        DriverAvailabilityServiceV3 $availabilityService,
        NotificationServiceV3 $notificationService,
        TripLifecycleNotifierV3 $notifier
    ) {
        $this->lifecycle = $lifecycle;
        $this->availabilityService = $availabilityService;
        $this->notificationService = $notificationService;
        $this->notifier = $notifier;
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
        if ($trip->match_attempt_count >= 5) {
            $trip->matching_timeout_at = now();
            $trip->save();
            $this->lifecycle->cancel($trip, 'NO_DRIVER_AVAILABLE');
            $this->notificationService->sendToPassenger($trip->user_id, [
                'type' => 'TRIP_REJECTED',
                'message' => 'No drivers available at the moment. Please try again.',
            ]);
            $this->notifier->dispatch($trip, 'trip.cancelled', [
                'trip_id' => $trip->id,
                'reason' => 'NO_DRIVER_AVAILABLE',
                'message' => 'No drivers available at the moment.',
            ]);
            $this->notifier->dispatch($trip, 'trip.trip.cancelled', [
                'trip_id' => $trip->id,
                'reason' => 'NO_DRIVER_AVAILABLE',
                'message' => 'No drivers available at the moment.',
            ]);
            return;
        }

        $selectedDriver = null;

        if ($trip->matched_driver_id) {
            $selectedDriver = Driver::query()
                ->where('id', $trip->matched_driver_id)
                ->where('status', 'approved')
                ->where('is_online', true)
                ->whereIn('availability_status', ['online', 'available'])
                ->first();
        }

        $useFallback = false;
        if (!$selectedDriver) {
            $ignoredIds = $trip->ignored_driver_ids ?? [];
            
            // Check if fallback matching should be used (elapsed time > 60 seconds)
            $elapsedSeconds = $trip->matching_started_at ? $trip->matching_started_at->diffInSeconds(now()) : 0;
            $useFallback = $elapsedSeconds > 60;
            $radiusKm = $useFallback ? 50.0 : 5.0;

            // Stage 1: Deterministic Nearest Available Driver Search
            $lat = $trip->pickup_lat ?? -1.95;
            $lng = $trip->pickup_lng ?? 30.06;
            $haversine = "( 6371 * acos( cos( radians($lat) ) * cos( radians( current_latitude ) ) * cos( radians( current_longitude ) - radians($lng) ) + sin( radians($lat) ) * sin( radians( current_latitude ) ) ) )";

            $query = Driver::query()
                ->select('drivers.*')
                ->selectRaw("$haversine AS distance")
                ->where('drivers.status', 'approved')
                ->where('drivers.is_online', true)
                ->whereIn('drivers.availability_status', ['online', 'available'])
                ->whereNull('drivers.current_trip_id');

            if (!$useFallback) {
                $query->whereRaw("$haversine <= ?", [$radiusKm]);
            }

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

            $selectedDriver = $query
                ->orderByRaw("$haversine ASC")
                ->first();
        }

        if (!$selectedDriver) {
            $trip->matching_timeout_at = now();
            $trip->save();
            $this->lifecycle->cancel($trip, 'NO_DRIVER_AVAILABLE');
            $this->notificationService->sendToPassenger($trip->user_id, [
                'type' => 'TRIP_REJECTED',
                'message' => 'No drivers available at the moment. Please try again.',
            ]);
            $this->notifier->dispatch($trip, 'trip.cancelled', [
                'trip_id' => $trip->id,
                'reason' => 'NO_DRIVER_AVAILABLE',
                'message' => 'No drivers available at the moment.',
            ]);
            $this->notifier->dispatch($trip, 'trip.trip.cancelled', [
                'trip_id' => $trip->id,
                'reason' => 'NO_DRIVER_AVAILABLE',
                'message' => 'No drivers available at the moment.',
            ]);
            return;
        }

        // Offer trip to selected driver. Final assignment happens only after accept.
        $trip->matched_driver_id = $selectedDriver->id;
        $trip->driver_response_status = 'pending';
        $trip->match_attempt_count += 1;
        $trip->last_matched_at = now();
        if ($useFallback) {
            $trip->fallback_match_used = true;
        }
        if ($trip->status !== 'MATCHING') {
            $this->lifecycle->transition($trip, 'MATCHING');
        } else {
            $trip->save();
        }

        $selectedDriver->loadMissing(['user', 'vehicle']);
        $expiresAt = now()->addMinutes(5);
        $payload = $this->offerPayload($trip, $selectedDriver, $expiresAt);

        DriverTripOffer::query()->create([
            'trip_id' => $trip->id,
            'driver_id' => $selectedDriver->id,
            'status' => 'pending',
            'expires_at' => $expiresAt,
            'payload' => $payload,
        ]);

        $this->notifier->dispatch($trip, 'trip.offer.created', $payload, $selectedDriver);

        // Notify Driver
        $trip->loadMissing('user');
        $passengerName = $trip->user?->name ?? 'Passenger';

        $this->notificationService->sendToDriver($selectedDriver->id, [
            'type' => 'NEW_TRIP_REQUEST',
            'trip_id' => $trip->id,
            'passenger_name' => $passengerName,
            'pickup' => $trip->pickup_location,
            'dropoff' => $trip->dropoff_location,
            'fare' => $trip->fare_estimate ?? 4500,
            'message' => 'New trip request from ' . $passengerName . '. Accept or reject.',
            'actions' => [
                'accept' => "/api/v3/trips/{$trip->id}/accept",
                'reject' => "/api/v3/trips/{$trip->id}/reject",
            ],
        ]);

        // Dispatch timeout handler
        HandleDriverTimeoutV3::dispatch($trip, $selectedDriver->id)->delay(now()->addMinutes(5));
    }

    private function offerPayload(TripV3 $trip, Driver $driver, \DateTimeInterface $expiresAt): array
    {
        $passengerName = $trip->user?->name ?? 'Passenger';
        $distance = $this->distanceKm(
            (float) ($trip->pickup_lat ?? 0),
            (float) ($trip->pickup_lng ?? 0),
            (float) ($trip->dropoff_lat ?? 0),
            (float) ($trip->dropoff_lng ?? 0),
        );

        return [
            'trip_id' => $trip->id,
            'driver_id' => $driver->id,
            'passenger_name' => $passengerName,
            'pickup_location' => $trip->pickup_location,
            'dropoff_location' => $trip->dropoff_location,
            'estimated_distance' => round($distance, 2),
            'estimated_fare' => (float) ($trip->fare_estimate ?? max(1500, $distance * 900)),
            'pickup_lat' => (float) $trip->pickup_lat,
            'pickup_lng' => (float) $trip->pickup_lng,
            'dropoff_lat' => (float) $trip->dropoff_lat,
            'dropoff_lng' => (float) $trip->dropoff_lng,
            'expires_at' => $expiresAt->format(DATE_ATOM),
        ];
    }

    private function distanceKm(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        if ($fromLat === 0.0 && $fromLng === 0.0 || $toLat === 0.0 && $toLng === 0.0) {
            return 0.0;
        }

        $earthRadiusKm = 6371;
        $latDelta = deg2rad($toLat - $fromLat);
        $lngDelta = deg2rad($toLng - $fromLng);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($lngDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
