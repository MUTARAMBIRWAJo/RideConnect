<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RideAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIController extends Controller
{
    public function __construct(private readonly RideAIService $rideAIService) {}

    public function matchDriver(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'traffic_level' => 'nullable|numeric|min:0|max:5',
            'max_results' => 'nullable|integer|min:1|max:20',
            'ride_type' => 'nullable|string|max:30',
            'drivers' => 'array',
            'drivers.*.driver_id' => 'required_with:drivers|integer',
            'drivers.*.lat' => 'required_with:drivers|numeric',
            'drivers.*.lng' => 'required_with:drivers|numeric',
        ]);

        return $this->respond($this->rideAIService->matchDriver($payload));
    }

    public function predictETA(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'origin_lat' => 'required|numeric|between:-90,90',
            'origin_lng' => 'required|numeric|between:-180,180',
            'destination_lat' => 'required|numeric|between:-90,90',
            'destination_lng' => 'required|numeric|between:-180,180',
            'traffic_level' => 'nullable|numeric|min:0|max:5',
            'time_of_day' => 'nullable|integer|min:0|max:23',
            'day_of_week' => 'nullable|integer|min:0|max:6',
            'road_type' => 'nullable|string|max:40',
            'weather' => 'nullable|string|max:40',
        ]);

        return $this->respond($this->rideAIService->predictETA($payload));
    }

    public function predictDemand(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'hour' => 'required|integer|min:0|max:23',
            'day_of_week' => 'required|integer|min:0|max:6',
        ]);

        return $this->respond($this->rideAIService->predictDemand($payload));
    }

    public function calculateSurge(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'distance' => 'required|numeric|min:0',
            'estimated_time' => 'nullable|numeric|min:0',
            'demand_density' => 'required|numeric|min:0',
            'driver_density' => 'nullable|numeric|min:0',
            'traffic_level' => 'nullable|numeric|min:0|max:5',
            'time_of_day' => 'nullable|integer|min:0|max:23',
            'day_of_week' => 'nullable|integer|min:0|max:6',
            'ride_type' => 'nullable|string|max:40',
        ]);

        return $this->respond($this->rideAIService->calculateSurge($payload));
    }

    public function optimizeRoute(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'pickup_lat' => 'required|numeric|between:-90,90',
            'pickup_lng' => 'required|numeric|between:-180,180',
            'dropoff_lat' => 'required|numeric|between:-90,90',
            'dropoff_lng' => 'required|numeric|between:-180,180',
            'traffic_level' => 'nullable|integer|min:1|max:5',
            'algorithm' => 'nullable|in:astar,dijkstra',
            'checkpoints' => 'nullable|array',
            'checkpoints.*.lat' => 'required_with:checkpoints|numeric|between:-90,90',
            'checkpoints.*.lng' => 'required_with:checkpoints|numeric|between:-180,180',
        ]);

        return $this->respond($this->rideAIService->optimizeRoute($payload));
    }

    public function analyzeDriver(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'driver_id' => 'nullable|integer|min:1',
            'avg_trip_duration_min' => 'required|numeric|min:1',
            'avg_speed_kmh' => 'required|numeric|min:1',
            'cancellation_rate' => 'required|numeric|min:0|max:1',
            'avg_rating' => 'required|numeric|min:1|max:5',
            'route_deviation_pct' => 'required|numeric|min:0',
            'total_rides' => 'required|integer|min:0',
        ]);

        return $this->respond($this->rideAIService->analyzeDriver($payload));
    }

    public function detectFareAnomaly(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'fare' => 'required|numeric|min:0.01',
            'distance_km' => 'required|numeric|min:0.01',
            'demand_level' => 'nullable|integer|min:1|max:5',
            'trip_id' => 'nullable|integer|min:1',
        ]);

        return $this->respond($this->rideAIService->detectFareAnomaly($payload));
    }

    public function driverRedistribution(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'max_suggestions' => 'nullable|integer|min:1|max:100',
        ]);

        return $this->respond($this->rideAIService->driverRedistribution($payload));
    }

    public function routeMonitor(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'driver_lat' => 'required|numeric|between:-90,90',
            'driver_lng' => 'required|numeric|between:-180,180',
            'destination_lat' => 'required|numeric|between:-90,90',
            'destination_lng' => 'required|numeric|between:-180,180',
        ]);

        return $this->respond($this->rideAIService->routeMonitor($payload));
    }

    public function driverIdle(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'idle_threshold_minutes' => 'nullable|integer|min:10|max:120',
            'movement_radius_m' => 'nullable|numeric|min:20|max:500',
        ]);

        return $this->respond($this->rideAIService->driverIdle($payload));
    }

    public function cancellationAnomalies(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'limit' => 'nullable|integer|min:10|max:500',
        ]);

        return $this->respond($this->rideAIService->cancellationAnomalies($payload));
    }

    public function systemHealth(): JsonResponse
    {
        return $this->respond($this->rideAIService->systemHealth());
    }

    private function respond(array $result): JsonResponse
    {
        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'AI service call failed',
            ], (int) ($result['status'] ?? 502));
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'] ?? [],
        ]);
    }
}
