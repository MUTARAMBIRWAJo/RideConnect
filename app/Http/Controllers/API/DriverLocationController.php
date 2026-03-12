<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DriverLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverLocationController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'driver_id' => 'required|integer|exists:drivers,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $location = DriverLocation::updateOrCreate(
            ['driver_id' => $validated['driver_id']],
            [
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'updated_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Driver location updated successfully',
            'data' => [
                'driver_id' => (int) $location->driver_id,
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'updated_at' => $location->updated_at?->toIso8601String(),
            ],
        ]);
    }
}
