<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DriverLocation;
use App\Services\AITrainingDataLogger;
use App\Services\Location\DriverLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverLocationController extends Controller
{
    public function __construct(
        private readonly AITrainingDataLogger $trainingDataLogger,
        private readonly DriverLocationService $locationService
    ) {
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'driver_id' => 'required|integer|exists:drivers,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'speed_kmh' => 'nullable|numeric|min:0|max:200',
            'heading' => 'nullable|numeric|between:0,360',
            'accuracy' => 'nullable|numeric|min:0|max:1000',
            'is_online' => 'nullable|boolean',
        ]);

        $location = $this->locationService->updateLocation(
            driverId: (int) $validated['driver_id'],
            latitude: (float) $validated['latitude'],
            longitude: (float) $validated['longitude'],
            speedKmh: isset($validated['speed_kmh']) ? (float) $validated['speed_kmh'] : null,
            heading: isset($validated['heading']) ? (float) $validated['heading'] : null,
            accuracy: isset($validated['accuracy']) ? (float) $validated['accuracy'] : null,
            isOnline: $validated['is_online'] ?? true,
        );

        $this->trainingDataLogger->logDriverLocation(
            (int) $validated['driver_id'],
            (float) $validated['latitude'],
            (float) $validated['longitude'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Driver location updated successfully',
            'data' => [
                'driver_id' => (int) $location->driver_id,
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'speed_kmh' => (float) $location->speed_kmh,
                'heading' => (float) $location->heading,
                'accuracy' => (float) $location->accuracy,
                'is_online' => (bool) $location->is_online,
                'updated_at' => $location->updated_at?->toIso8601String(),
                'last_activity_at' => $location->last_activity_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get current location of a driver
     */
    public function getLocation(Request $request, int $driverId): JsonResponse
    {
        $location = $this->locationService->getCurrentLocation($driverId);

        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'Driver location not found',
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
                'updated_at' => $location->updated_at?->toIso8601String(),
                'last_activity_at' => $location->last_activity_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get nearby online drivers
     */
    public function getNearbyDrivers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_km' => 'nullable|numeric|min:0.1|max:50',
        ]);

        $drivers = $this->locationService->getNearbyDrivers(
            latitude: (float) $validated['latitude'],
            longitude: (float) $validated['longitude'],
            radiusKm: $validated['radius_km'] ?? 5.0
        );

        return response()->json([
            'success' => true,
            'data' => $drivers,
        ]);
    }
}
