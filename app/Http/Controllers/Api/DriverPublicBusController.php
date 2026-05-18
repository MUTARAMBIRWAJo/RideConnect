<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\PassengerRouteBoarding;
use App\Services\PublicBusTransportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverPublicBusController extends Controller
{
    public function __construct(private readonly PublicBusTransportService $busService) {}

    public function location(Request $request): JsonResponse
    {
        $driver = $request->user()->driver;
        if (! $driver) {
            return response()->json(['success' => false, 'message' => 'Driver profile not found'], 404);
        }

        $validated = $request->validate([
            'bus_route_assignment_id' => 'required|integer|exists:bus_route_assignments,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'speed_kph' => 'nullable|numeric|min:0',
            'heading_degrees' => 'nullable|integer|min:0|max:360',
            'next_stop_id' => 'nullable|integer|exists:corridor_stops,id',
            'eta_minutes' => 'nullable|integer|min:0',
            'route_progress_percent' => 'nullable|numeric|min:0|max:100',
            'captured_at' => 'nullable|date',
        ]);

        $update = $this->busService->updateLocation((int) $validated['bus_route_assignment_id'], $validated);

        return response()->json(['success' => true, 'data' => $update]);
    }

    public function arrivedStop(Request $request): JsonResponse
    {
        $driver = $request->user()->driver;
        if (! $driver) {
            return response()->json(['success' => false, 'message' => 'Driver profile not found'], 404);
        }

        $validated = $request->validate([
            'bus_route_assignment_id' => 'required|integer|exists:bus_route_assignments,id',
            'corridor_stop_id' => 'required|integer|exists:corridor_stops,id',
            'trip_id' => 'nullable|integer|exists:trips,id',
            'metadata' => 'nullable|array',
        ]);

        try {
            $arrival = $this->busService->markArrivedStop(
                (int) $validated['bus_route_assignment_id'],
                (int) $validated['corridor_stop_id'],
                $validated['trip_id'] ?? null,
                $validated['metadata'] ?? []
            );
        } catch (DomainException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'error_code' => $exception->getErrorCode(),
            ], 422);
        }

        return response()->json(['success' => true, 'data' => $arrival]);
    }

    public function passengerBoarded(Request $request): JsonResponse
    {
        $driver = $request->user()->driver;
        if (! $driver) {
            return response()->json(['success' => false, 'message' => 'Driver profile not found'], 404);
        }

        $validated = $request->validate([
            'passenger_route_boarding_id' => 'required|integer|exists:passenger_route_boardings,id',
            'metadata' => 'nullable|array',
        ]);

        try {
            $event = $this->busService->markPassengerBoarded(
                (int) $validated['passenger_route_boarding_id'],
                (int) $driver->id,
                $validated['metadata'] ?? []
            );
        } catch (DomainException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'error_code' => $exception->getErrorCode(),
            ], 422);
        }

        return response()->json(['success' => true, 'data' => $event]);
    }

    public function passengerCompleted(Request $request): JsonResponse
    {
        $driver = $request->user()->driver;
        if (! $driver) {
            return response()->json(['success' => false, 'message' => 'Driver profile not found'], 404);
        }

        $validated = $request->validate([
            'passenger_route_boarding_id' => 'required|integer|exists:passenger_route_boardings,id',
            'metadata' => 'nullable|array',
        ]);

        try {
            $boarding = $this->busService->markPassengerCompleted(
                (int) $validated['passenger_route_boarding_id'],
                (int) $driver->id,
                $validated['metadata'] ?? []
            );
        } catch (DomainException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'error_code' => $exception->getErrorCode(),
            ], 422);
        }

        return response()->json(['success' => true, 'data' => $boarding]);
    }
}
