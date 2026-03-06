<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripController extends Controller
{
    /**
     * Display a listing of trips.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = Trip::query();
        
        // Role-based filtering
        if ($user->role->isSuperAdmin() || $user->role->isManager()) {
            // Admins can see all trips
        } else {
            // Regular users can only see their own trips (as passenger or driver)
            $query->where(function ($q) use ($user) {
                $q->where('passenger_id', $user->id)
                  ->orWhere('driver_id', $user->id);
            });
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        $trips = $query->orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'data' => $trips->map(fn($trip) => [
                'id' => $trip->id,
                'passenger' => [
                    'id' => $trip->passenger?->id,
                    'name' => $trip->passenger?->name,
                ],
                'driver' => [
                    'id' => $trip->driver?->id,
                    'name' => $trip->driver?->name,
                ],
                'pickup_location' => $trip->pickup_location,
                'dropoff_location' => $trip->dropoff_location,
                'fare' => $trip->fare,
                'status' => $trip->status,
                'requested_at' => $trip->requested_at?->toIso8601String(),
                'started_at' => $trip->started_at?->toIso8601String(),
                'completed_at' => $trip->completed_at?->toIso8601String(),
                'created_at' => $trip->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Display the specified trip.
     */
    public function show(int $id): JsonResponse
    {
        $trip = Trip::findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $trip->id,
                'passenger' => [
                    'id' => $trip->passenger?->id,
                    'name' => $trip->passenger?->name,
                    'email' => $trip->passenger?->email,
                    'phone' => $trip->passenger?->phone,
                ],
                'driver' => [
                    'id' => $trip->driver?->id,
                    'name' => $trip->driver?->name,
                    'email' => $trip->driver?->email,
                    'phone' => $trip->driver?->phone,
                ],
                'pickup_location' => $trip->pickup_location,
                'pickup_lat' => $trip->pickup_lat,
                'pickup_lng' => $trip->pickup_lng,
                'dropoff_location' => $trip->dropoff_location,
                'dropoff_lat' => $trip->dropoff_lat,
                'dropoff_lng' => $trip->dropoff_lng,
                'fare' => $trip->fare,
                'status' => $trip->status,
                'requested_at' => $trip->requested_at?->toIso8601String(),
                'started_at' => $trip->started_at?->toIso8601String(),
                'completed_at' => $trip->completed_at?->toIso8601String(),
                'created_at' => $trip->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Create a new trip request (Passenger).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Check if user is approved
        if (!$user->is_approved) {
            return response()->json([
                'success' => false,
                'message' => 'Your account must be approved to request trips',
            ], 403);
        }
        
        $validated = $request->validate([
            'pickup_location' => 'required|string',
            'pickup_lat' => 'required|numeric|between:-90,90',
            'pickup_lng' => 'required|numeric|between:-180,180',
            'dropoff_location' => 'required|string',
            'dropoff_lat' => 'required|numeric|between:-90,90',
            'dropoff_lng' => 'required|numeric|between:-180,180',
            'fare' => 'required|numeric|min:0',
        ]);
        
        $trip = Trip::create([
            ...$validated,
            'passenger_id' => $user->id,
            'driver_id' => null,
            'status' => 'PENDING',
            'requested_at' => now(),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Trip request created successfully',
            'data' => [
                'id' => $trip->id,
                'status' => $trip->status,
            ],
        ], 201);
    }

    /**
     * Accept a trip request (Driver).
     */
    public function accept(Request $request, int $id): JsonResponse
    {
        $trip = Trip::findOrFail($id);
        $user = $request->user();
        
        // Check if user is a driver
        if (!$user->isDriver()) {
            return response()->json([
                'success' => false,
                'message' => 'Only drivers can accept trip requests',
            ], 403);
        }
        
        // Check if trip is pending
        if ($trip->status !== 'PENDING') {
            return response()->json([
                'success' => false,
                'message' => 'This trip is not pending',
            ], 400);
        }
        
        // Get driver profile
        $driver = $user->driver;
        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found',
            ], 404);
        }
        
        $trip->update([
            'driver_id' => $driver->id,
            'status' => 'ACCEPTED',
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Trip accepted successfully',
            'data' => [
                'id' => $trip->id,
                'status' => $trip->status,
            ],
        ]);
    }

    /**
     * Start a trip (Driver).
     */
    public function start(int $id): JsonResponse
    {
        $trip = Trip::findOrFail($id);
        $user = request()->user();
        
        // Check if user is the driver of this trip
        if ($trip->driver?->user_id !== $user->id && !$user->role->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only the assigned driver can start this trip',
            ], 403);
        }
        
        // Check if trip is accepted
        if ($trip->status !== 'ACCEPTED') {
            return response()->json([
                'success' => false,
                'message' => 'Trip must be accepted before starting',
            ], 400);
        }
        
        $trip->update([
            'status' => 'STARTED',
            'started_at' => now(),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Trip started successfully',
            'data' => [
                'id' => $trip->id,
                'status' => $trip->status,
                'started_at' => $trip->started_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Complete a trip (Driver).
     */
    public function complete(int $id): JsonResponse
    {
        $trip = Trip::findOrFail($id);
        $user = request()->user();
        
        // Check if user is the driver of this trip
        if ($trip->driver?->user_id !== $user->id && !$user->role->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only the assigned driver can complete this trip',
            ], 403);
        }
        
        // Check if trip is started
        if ($trip->status !== 'STARTED') {
            return response()->json([
                'success' => false,
                'message' => 'Trip must be started before completing',
            ], 400);
        }
        
        $trip->update([
            'status' => 'COMPLETED',
            'completed_at' => now(),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Trip completed successfully',
            'data' => [
                'id' => $trip->id,
                'status' => $trip->status,
                'completed_at' => $trip->completed_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Cancel a trip.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $trip = Trip::findOrFail($id);
        $user = $request->user();
        
        // Check if user is passenger, driver, or admin
        $isPassenger = $trip->passenger_id === $user->id;
        $isDriver = $trip->driver?->user_id === $user->id;
        $isAdmin = $user->role->isSuperAdmin() || $user->role->isManager();
        
        if (!$isPassenger && !$isDriver && !$isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to cancel this trip',
            ], 403);
        }
        
        // Cannot cancel if already completed
        if ($trip->status === 'COMPLETED') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel a completed trip',
            ], 400);
        }
        
        $trip->update([
            'status' => 'CANCELLED',
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Trip cancelled successfully',
        ]);
    }

    /**
     * Get current user's trips.
     */
    public function myTrips(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = Trip::query();
        
        // Filter by role (passenger or driver)
        $type = $request->get('type', 'all'); // 'passenger', 'driver', or 'all'
        
        if ($type === 'passenger') {
            $query->where('passenger_id', $user->id);
        } elseif ($type === 'driver') {
            $query->where('driver_id', $user->id);
        } else {
            $query->where(function ($q) use ($user) {
                $q->where('passenger_id', $user->id)
                  ->orWhere('driver_id', $user->id);
            });
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        $trips = $query->orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'data' => $trips->map(fn($trip) => [
                'id' => $trip->id,
                'type' => $trip->passenger_id === $user->id ? 'passenger' : 'driver',
                'passenger' => [
                    'id' => $trip->passenger?->id,
                    'name' => $trip->passenger?->name,
                ],
                'driver' => [
                    'id' => $trip->driver?->id,
                    'name' => $trip->driver?->name,
                ],
                'pickup_location' => $trip->pickup_location,
                'dropoff_location' => $trip->dropoff_location,
                'fare' => $trip->fare,
                'status' => $trip->status,
                'requested_at' => $trip->requested_at?->toIso8601String(),
                'started_at' => $trip->started_at?->toIso8601String(),
                'completed_at' => $trip->completed_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Get pending trip requests (for drivers).
     */
    public function pendingRequests(): JsonResponse
    {
        $trips = Trip::where('status', 'PENDING')
            ->whereNull('driver_id')
            ->orderBy('requested_at', 'asc')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $trips->map(fn($trip) => [
                'id' => $trip->id,
                'passenger' => [
                    'id' => $trip->passenger?->id,
                    'name' => $trip->passenger?->name,
                    'phone' => $trip->passenger?->phone,
                ],
                'pickup_location' => $trip->pickup_location,
                'pickup_lat' => $trip->pickup_lat,
                'pickup_lng' => $trip->pickup_lng,
                'dropoff_location' => $trip->dropoff_location,
                'dropoff_lat' => $trip->dropoff_lat,
                'dropoff_lng' => $trip->dropoff_lng,
                'fare' => $trip->fare,
                'requested_at' => $trip->requested_at?->toIso8601String(),
            ]),
        ]);
    }
}
