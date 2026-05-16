<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\DriverBehavior;
use App\Models\PassengerBehavior;
use App\Models\RouteState;
use App\Models\Trip;
use App\Services\TripConditionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InternalDataController extends Controller
{
    public function __construct(private readonly TripConditionService $conditionService) {}

    public function driverBehavior(int $driverId): JsonResponse
    {
        $behavior = DriverBehavior::query()
            ->where('driver_id', $driverId)
            ->orderByDesc('created_at')
            ->first();

        if (! $behavior) {
            return response()->json([
                'success' => false,
                'message' => 'Driver behavior not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $behavior,
        ]);
    }

    public function passengerBehavior(int $passengerId): JsonResponse
    {
        $behavior = PassengerBehavior::query()
            ->where('user_id', $passengerId)
            ->orWhere('passenger_id', $passengerId)
            ->orderByDesc('created_at')
            ->first();

        if (! $behavior) {
            return response()->json([
                'success' => false,
                'message' => 'Passenger behavior not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $behavior,
        ]);
    }

    public function routeState(Request $request): JsonResponse
    {
        $request->validate([
            'trip_id' => 'nullable|integer|min:1',
            'route_id' => 'nullable|integer|min:1',
            'pickup_lat' => 'nullable|numeric|between:-90,90',
            'pickup_lng' => 'nullable|numeric|between:-180,180',
            'dropoff_lat' => 'nullable|numeric|between:-90,90',
            'dropoff_lng' => 'nullable|numeric|between:-180,180',
        ]);

        if ($request->filled('trip_id')) {
            $trip = Trip::findOrFail($request->input('trip_id'));
            if ($trip->routeState) {
                return response()->json(['success' => true, 'data' => $trip->routeState]);
            }

            if ($trip->ride?->route_id) {
                $routeState = RouteState::query()
                    ->where('route_id', $trip->ride->route_id)
                    ->latest('created_at')
                    ->first();

                if ($routeState) {
                    return response()->json(['success' => true, 'data' => $routeState]);
                }
            }
        }

        if ($request->filled('route_id')) {
            $routeState = RouteState::query()
                ->where('route_id', $request->integer('route_id'))
                ->latest('created_at')
                ->first();

            if ($routeState) {
                return response()->json(['success' => true, 'data' => $routeState]);
            }
        }

        if ($request->filled('pickup_lat') && $request->filled('pickup_lng') && $request->filled('dropoff_lat') && $request->filled('dropoff_lng')) {
            return response()->json([
                'success' => true,
                'data' => $this->conditionService->getCurrentRouteState(
                    (float) $request->input('pickup_lat'),
                    (float) $request->input('pickup_lng'),
                    (float) $request->input('dropoff_lat'),
                    (float) $request->input('dropoff_lng'),
                    $request->filled('route_id') ? $request->integer('route_id') : null
                ),
            ]);
        }

        throw ValidationException::withMessages([
            'trip_id' => 'A trip_id, route_id, or full pickup/dropoff coordinates are required to resolve route state.',
        ]);
    }

    public function weather(Request $request): JsonResponse
    {
        $request->validate([
            'trip_id' => 'nullable|integer|min:1',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ]);

        if ($request->filled('trip_id')) {
            $trip = Trip::findOrFail($request->input('trip_id'));
            if ($trip->weatherCondition) {
                return response()->json(['success' => true, 'data' => $trip->weatherCondition]);
            }
        }

        if ($request->filled('lat') && $request->filled('lng')) {
            return response()->json([
                'success' => true,
                'data' => $this->conditionService->getCurrentWeatherState(
                    (float) $request->input('lat'),
                    (float) $request->input('lng')
                ),
            ]);
        }

        throw ValidationException::withMessages([
            'lat' => 'A trip_id or lat/lng coordinates are required to resolve weather.',
        ]);
    }
}
