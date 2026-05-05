<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Services\Location\DriverLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DriverTrackingController handles real-time driver location tracking for passengers.
 */
class DriverTrackingController extends Controller
{
    public function __construct(
        private readonly DriverLocationService $driverLocationService
    ) {}

    /**
     * GET /api/driver-tracking/{driverId}
     * Get current location of a specific driver
     */
    public function getDriverLocation(Request $request, int $driverId): JsonResponse
    {
        $location = $this->driverLocationService->getCurrentLocation($driverId);

        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'Driver location not available',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'driver_id' => (int) $location->driver_id,
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'speed_kmh' => (float) $location->speed_kmh,
                'heading' => (float) $location->heading,
                'accuracy' => (float) $location->accuracy,
                'is_online' => (bool) $location->is_online,
                'last_updated' => $location->updated_at?->toIso8601String(),
                'last_activity' => $location->last_activity_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/driver-tracking/trip/{tripId}
     * Get current driver location for a specific trip
     */
    public function getTripDriverLocation(Request $request, int $tripId): JsonResponse
    {
        $trip = Trip::with('driver')->findOrFail($tripId);

        if (!$trip->driver) {
            return response()->json([
                'success' => false,
                'message' => 'Trip has no assigned driver',
            ], 404);
        }

        $location = $this->driverLocationService->getCurrentLocation($trip->driver->id);

        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'Driver location not available',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'trip_id' => $tripId,
                'driver_id' => (int) $location->driver_id,
                'driver_name' => $trip->driver->user->name ?? 'Unknown Driver',
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'speed_kmh' => (float) $location->speed_kmh,
                'heading' => (float) $location->heading,
                'accuracy' => (float) $location->accuracy,
                'is_online' => (bool) $location->is_online,
                'last_updated' => $location->updated_at?->toIso8601String(),
                'last_activity' => $location->last_activity_at?->toIso8601String(),
                'trip_status' => $trip->status,
            ],
        ]);
    }

    /**
     * GET /api/driver-tracking/nearby
     * Get nearby online drivers for a location
     */
    public function getNearbyDrivers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_km' => 'nullable|numeric|min:0.1|max:50',
        ]);

        $drivers = $this->driverLocationService->getNearbyDrivers(
            latitude: (float) $validated['latitude'],
            longitude: (float) $validated['longitude'],
            radiusKm: $validated['radius_km'] ?? 5.0
        );

        return response()->json([
            'success' => true,
            'data' => array_map(function ($driver) {
                return [
                    'driver_id' => (int) $driver['driver_id'],
                    'latitude' => (float) $driver['latitude'],
                    'longitude' => (float) $driver['longitude'],
                    'speed_kmh' => (float) $driver['speed_kmh'],
                    'heading' => (float) $driver['heading'],
                    'accuracy' => (float) $driver['accuracy'],
                    'is_online' => (bool) $driver['is_online'],
                    'distance_km' => (float) $driver['distance_km'],
                    'last_updated' => $driver['updated_at']?->toIso8601String(),
                    'last_activity' => $driver['last_activity_at']?->toIso8601String(),
                ];
            }, $drivers),
        ]);
    }
}
