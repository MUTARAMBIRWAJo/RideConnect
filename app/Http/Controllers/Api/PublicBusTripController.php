<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Services\PublicBusTripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicBusTripController extends Controller
{
    public function __construct(private PublicBusTripService $service) {}

    /**
     * POST /api/v1/passenger/public-bus/trip-request
     */
    public function tripRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pickup_location' => 'required|string',
            'dropoff_location' => 'required|string',
            'pickup_lat' => 'required|numeric',
            'pickup_lng' => 'required|numeric',
            'dropoff_lat' => 'required|numeric',
            'dropoff_lng' => 'required|numeric',
            'preferred_time' => 'nullable|string', // now | scheduled
        ]);

        $passengerId = $request->user()->id ?? 1; // Fallback for testing if no auth

        $trip = $this->service->requestTrip($validated, $passengerId);

        return response()->json([
            'success' => true,
            'message' => 'Trip requested',
            'data' => $trip
        ], 201);
    }

    /**
     * POST /api/v1/trips/{id}/board
     */
    public function board(Request $request, string $id): JsonResponse
    {
        $trip = Trip::findOrFail($id);

        try {
            $this->service->board($trip);
            return response()->json(['success' => true, 'message' => 'Boarded successfully', 'data' => $trip]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/v1/trips/{id}/start
     */
    public function start(Request $request, string $id): JsonResponse
    {
        $trip = Trip::findOrFail($id);

        try {
            $this->service->start($trip);
            return response()->json(['success' => true, 'message' => 'Trip started', 'data' => $trip]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/v1/trips/{id}/complete
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        $trip = Trip::findOrFail($id);

        try {
            $this->service->complete($trip);
            return response()->json(['success' => true, 'message' => 'Trip completed', 'data' => $trip]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
