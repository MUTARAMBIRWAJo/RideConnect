<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Services\DriverLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DriverLocationController extends Controller
{
    private DriverLocationService $locationService;

    public function __construct(DriverLocationService $locationService)
    {
        $this->locationService = $locationService;
    }

    /**
     * POST /api/v1/driver/location/update
     * Update driver's current location
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'lat' => 'required|numeric|between:-90,90',
                'lng' => 'required|numeric|between:-180,180',
                'speed' => 'sometimes|numeric|min:0',
                'heading' => 'sometimes|integer|between:0,360',
                'accuracy' => 'sometimes|numeric|min:0',
                'trip_id' => 'sometimes|integer|exists:motorcycle_trips,id',
            ]);

            // Get driver from authenticated user
            $driver = auth()->user()->driver;
            if (!$driver) {
                Log::warning('DriverLocationController: Driver profile not found', [
                    'user_id' => auth()->id(),
                ]);
                return response()->json([
                    'success' => false,
                    'error_code' => 'DRIVER_NOT_FOUND',
                    'message' => 'Driver profile not found',
                ], 404);
            }

            // Update location
            $result = $this->locationService->updateLocation(
                $driver,
                (float) $validated['lat'],
                (float) $validated['lng'],
                (float) ($validated['speed'] ?? null),
                (int) ($validated['heading'] ?? null),
                (float) ($validated['accuracy'] ?? null),
                (int) ($validated['trip_id'] ?? null)
            );

            if (!$result['success']) {
                $statusCode = $result['throttled'] ? 429 : 400;
                return response()->json($result, $statusCode);
            }

            return response()->json([
                'success' => true,
                'message' => 'Location updated successfully',
                'driver_id' => $driver->id,
                'timestamp' => now()->toIso8601String(),
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('DriverLocationController: Exception during location update', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'error_code' => 'INTERNAL_ERROR',
                'message' => 'Failed to update location',
            ], 500);
        }
    }

    /**
     * GET /api/v1/driver/location/current
     * Get driver's current location
     */
    public function current(Request $request): JsonResponse
    {
        try {
            $driver = auth()->user()->driver;
            if (!$driver) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'DRIVER_NOT_FOUND',
                    'message' => 'Driver profile not found',
                ], 404);
            }

            $location = $this->locationService->getCurrentLocation($driver);

            if (!$location) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'NO_LOCATION',
                    'message' => 'No location data available',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'driver_id' => $driver->id,
                    'lat' => $location->lat,
                    'lng' => $location->lng,
                    'speed' => $location->speed,
                    'heading' => $location->heading,
                    'accuracy' => $location->accuracy,
                    'recorded_at' => $location->recorded_at->toIso8601String(),
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('DriverLocationController: Exception fetching current location', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'error_code' => 'INTERNAL_ERROR',
                'message' => 'Failed to fetch location',
            ], 500);
        }
    }
}
