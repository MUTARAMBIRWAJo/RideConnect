<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoogleRouteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * RouteController - Secure Backend Route Engine
 *
 * Handles all route computation requests from Flutter mobile app.
 * Flutter NEVER calls Google Maps API directly - all requests go through this controller.
 */
class RouteController extends Controller
{
    private GoogleRouteService $routeService;

    public function __construct(GoogleRouteService $routeService)
    {
        $this->routeService = $routeService;
    }

    /**
     * POST /api/v1/route/compute
     *
     * Compute route between two coordinates
     *
     * Request:
     * {
     *     "origin_lat": -1.9534,
     *     "origin_lng": 30.0596,
     *     "dest_lat": -1.9848,
     *     "dest_lng": 30.1324
     * }
     *
     * Response:
     * {
     *     "success": true,
     *     "data": {
     *         "polyline": "encoded_string",
     *         "distance_meters": 5120,
     *         "distance_km": 5.12,
     *         "duration": "600s"
     *     }
     * }
     */
    public function compute(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'origin_lat' => 'required|numeric|between:-90,90',
                'origin_lng' => 'required|numeric|between:-180,180',
                'dest_lat' => 'required|numeric|between:-90,90',
                'dest_lng' => 'required|numeric|between:-180,180',
            ]);

            $route = $this->routeService->computeRoute(
                [
                    'lat' => (float) $validated['origin_lat'],
                    'lng' => (float) $validated['origin_lng'],
                ],
                [
                    'lat' => (float) $validated['dest_lat'],
                    'lng' => (float) $validated['dest_lng'],
                ]
            );

            if (!$route['success']) {
                Log::warning('Route computation failed', [
                    'origin' => [$validated['origin_lat'], $validated['origin_lng']],
                    'destination' => [$validated['dest_lat'], $validated['dest_lng']],
                    'error' => $route['error'] ?? 'Unknown error',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $route['error'] ?? 'Route service unavailable',
                ], 503);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'polyline' => $route['polyline'],
                    'distance_meters' => $route['distance_meters'],
                    'distance_km' => $route['distance_km'],
                    'duration' => $route['duration'],
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request parameters',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('RouteController Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * GET /api/v1/route/distance
     *
     * Get distance between two coordinates
     *
     * Query parameters:
     * - origin_lat, origin_lng, dest_lat, dest_lng
     *
     * Response:
     * {
     *     "success": true,
     *     "distance_meters": 5120,
     *     "distance_km": 5.12
     * }
     */
    public function distance(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'origin_lat' => 'required|numeric|between:-90,90',
                'origin_lng' => 'required|numeric|between:-180,180',
                'dest_lat' => 'required|numeric|between:-90,90',
                'dest_lng' => 'required|numeric|between:-180,180',
            ]);

            $distanceMeters = $this->routeService->getDistanceMeters(
                [
                    'lat' => (float) $validated['origin_lat'],
                    'lng' => (float) $validated['origin_lng'],
                ],
                [
                    'lat' => (float) $validated['dest_lat'],
                    'lng' => (float) $validated['dest_lng'],
                ]
            );

            return response()->json([
                'success' => true,
                'distance_meters' => $distanceMeters,
                'distance_km' => round($distanceMeters / 1000, 2),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid parameters',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * GET /api/v1/route/duration
     *
     * Get duration between two coordinates
     *
     * Query parameters:
     * - origin_lat, origin_lng, dest_lat, dest_lng
     *
     * Response:
     * {
     *     "success": true,
     *     "duration": "600s"
     * }
     */
    public function duration(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'origin_lat' => 'required|numeric|between:-90,90',
                'origin_lng' => 'required|numeric|between:-180,180',
                'dest_lat' => 'required|numeric|between:-90,90',
                'dest_lng' => 'required|numeric|between:-180,180',
            ]);

            $duration = $this->routeService->getDuration(
                [
                    'lat' => (float) $validated['origin_lat'],
                    'lng' => (float) $validated['origin_lng'],
                ],
                [
                    'lat' => (float) $validated['dest_lat'],
                    'lng' => (float) $validated['dest_lng'],
                ]
            );

            return response()->json([
                'success' => true,
                'duration' => $duration,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid parameters',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * GET /api/v1/route/polyline
     *
     * Get encoded polyline for route visualization
     *
     * Query parameters:
     * - origin_lat, origin_lng, dest_lat, dest_lng
     *
     * Response:
     * {
     *     "success": true,
     *     "polyline": "encoded_polyline_string"
     * }
     */
    public function polyline(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'origin_lat' => 'required|numeric|between:-90,90',
                'origin_lng' => 'required|numeric|between:-180,180',
                'dest_lat' => 'required|numeric|between:-90,90',
                'dest_lng' => 'required|numeric|between:-180,180',
            ]);

            $polyline = $this->routeService->getPolyline(
                [
                    'lat' => (float) $validated['origin_lat'],
                    'lng' => (float) $validated['origin_lng'],
                ],
                [
                    'lat' => (float) $validated['dest_lat'],
                    'lng' => (float) $validated['dest_lng'],
                ]
            );

            return response()->json([
                'success' => true,
                'polyline' => $polyline,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid parameters',
                'errors' => $e->errors(),
            ], 422);
        }
    }
}
