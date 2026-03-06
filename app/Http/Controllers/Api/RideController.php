<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RideController extends Controller
{
    private const TICKET_THRESHOLD_HOURS = 6;

    /**
     * Display a listing of rides.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Ride::with(['driver.user', 'vehicle']);
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by origin/destination (search)
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('origin_address', 'ilike', "%{$search}%")
                  ->orWhere('destination_address', 'ilike', "%{$search}%");
            });
        }
        
        // Filter by date
        if ($request->has('date')) {
            $query->whereDate('departure_time', $request->date);
        }
        
        // Filter available rides
        if ($request->has('available_only') && $request->available_only) {
            $query->where('available_seats', '>', 0)
                  ->where('status', 'ACTIVE')
                  ->where('departure_time', '>', now());
        }
        
        $rides = $query->orderBy('departure_time', 'asc')->get();
        
        return response()->json([
            'success' => true,
            'data' => $rides->map(fn($ride) => [
                'id' => $ride->id,
                'driver' => [
                    'id' => $ride->driver?->user?->id,
                    'name' => $ride->driver?->user?->name,
                ],
                'vehicle' => [
                    'id' => $ride->vehicle?->id,
                    'make' => $ride->vehicle?->make,
                    'model' => $ride->vehicle?->model,
                    'color' => $ride->vehicle?->color,
                    'license_plate' => $ride->vehicle?->license_plate,
                ],
                'origin' => [
                    'address' => $ride->origin_address,
                    'lat' => $ride->origin_lat,
                    'lng' => $ride->origin_lng,
                ],
                'destination' => [
                    'address' => $ride->destination_address,
                    'lat' => $ride->destination_lat,
                    'lng' => $ride->destination_lng,
                ],
                'departure_time' => $ride->departure_time->toIso8601String(),
                'arrival_time_estimated' => $ride->arrival_time_estimated?->toIso8601String(),
                'available_seats' => $ride->available_seats,
                'price_per_seat' => $ride->price_per_seat,
                'currency' => $ride->currency,
                'status' => $ride->status,
                'ride_type' => $ride->ride_type,
                'luggage_allowed' => $ride->luggage_allowed,
                'pets_allowed' => $ride->pets_allowed,
                'smoking_allowed' => $ride->smoking_allowed,
                'description' => $ride->description,
                'created_at' => $ride->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Display the specified ride.
     */
    public function show(int $id): JsonResponse
    {
        $ride = Ride::with(['driver.user', 'vehicle', 'bookings', 'reviews'])->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $ride->id,
                'driver' => [
                    'id' => $ride->driver?->user?->id,
                    'name' => $ride->driver?->user?->name,
                    'phone' => $ride->driver?->user?->phone,
                    'rating' => $ride->driver?->rating,
                ],
                'vehicle' => [
                    'id' => $ride->vehicle?->id,
                    'make' => $ride->vehicle?->make,
                    'model' => $ride->vehicle?->model,
                    'color' => $ride->vehicle?->color,
                    'license_plate' => $ride->vehicle?->license_plate,
                ],
                'origin' => [
                    'address' => $ride->origin_address,
                    'lat' => $ride->origin_lat,
                    'lng' => $ride->origin_lng,
                ],
                'destination' => [
                    'address' => $ride->destination_address,
                    'lat' => $ride->destination_lat,
                    'lng' => $ride->destination_lng,
                ],
                'departure_time' => $ride->departure_time->toIso8601String(),
                'arrival_time_estimated' => $ride->arrival_time_estimated?->toIso8601String(),
                'available_seats' => $ride->available_seats,
                'price_per_seat' => $ride->price_per_seat,
                'currency' => $ride->currency,
                'status' => $ride->status,
                'ride_type' => $ride->ride_type,
                'luggage_allowed' => $ride->luggage_allowed,
                'pets_allowed' => $ride->pets_allowed,
                'smoking_allowed' => $ride->smoking_allowed,
                'description' => $ride->description,
                'bookings_count' => $ride->bookings->count(),
                'reviews_count' => $ride->reviews->count(),
                'average_rating' => $ride->reviews->avg('rating'),
                'created_at' => $ride->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Create a new ride (Driver only).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Only drivers can create rides
        if (!$user->isDriver()) {
            return response()->json([
                'success' => false,
                'message' => 'Only drivers can create rides',
            ], 403);
        }
        
        // Check if user is approved
        if (!$user->is_approved) {
            return response()->json([
                'success' => false,
                'message' => 'Your account must be approved to create rides',
            ], 403);
        }
        
        $validated = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'origin_address' => 'required|string',
            'origin_lat' => 'required|numeric|between:-90,90',
            'origin_lng' => 'required|numeric|between:-180,180',
            'destination_address' => 'required|string',
            'destination_lat' => 'required|numeric|between:-90,90',
            'destination_lng' => 'required|numeric|between:-180,180',
            'departure_time' => 'required|date|after:now',
            'arrival_time_estimated' => 'nullable|date|after:departure_time',
            'available_seats' => 'required|integer|min:1|max:8',
            'price_per_seat' => 'required|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'description' => 'nullable|string',
            'ride_type' => 'sometimes|string|in:REGULAR,EXPRESS,SHUTTLE',
            'luggage_allowed' => 'sometimes|boolean',
            'pets_allowed' => 'sometimes|boolean',
            'smoking_allowed' => 'sometimes|boolean',
        ]);
        
        // Get driver profile
        $driver = $user->driver;
        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found',
            ], 404);
        }
        
        // Verify vehicle belongs to driver
        if ($driver->id !== $request->vehicle_id) {
            // Check if vehicle belongs to driver
            $vehicle = \App\Models\Vehicle::where('id', $request->vehicle_id)
                ->where('driver_id', $driver->id)
                ->first();
            
            if (!$vehicle) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vehicle not found or does not belong to you',
                ], 403);
            }
        }
        
        $ride = Ride::create([
            ...$validated,
            'driver_id' => $driver->id,
            'status' => 'ACTIVE',
                'currency' => $validated['currency'] ?? 'RWF',
            'ride_type' => $validated['ride_type'] ?? 'REGULAR',
            'luggage_allowed' => $validated['luggage_allowed'] ?? true,
            'pets_allowed' => $validated['pets_allowed'] ?? false,
            'smoking_allowed' => $validated['smoking_allowed'] ?? false,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Ride created successfully',
            'data' => [
                'id' => $ride->id,
                'status' => $ride->status,
            ],
        ], 201);
    }

    /**
     * Update the specified ride.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $ride = Ride::findOrFail($id);
        $user = $request->user();
        
        // Only the driver who created the ride can update it
        if ($ride->driver?->user_id !== $user->id && !$user->role->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this ride',
            ], 403);
        }
        
        // Cannot update if ride has already started
        if (in_array($ride->status, ['STARTED', 'COMPLETED', 'CANCELLED'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update ride that has already started, completed, or cancelled',
            ], 400);
        }
        
        $validated = $request->validate([
            'origin_address' => 'sometimes|string',
            'origin_lat' => 'sometimes|numeric|between:-90,90',
            'origin_lng' => 'sometimes|numeric|between:-180,180',
            'destination_address' => 'sometimes|string',
            'destination_lat' => 'sometimes|numeric|between:-90,90',
            'destination_lng' => 'sometimes|numeric|between:-180,180',
            'departure_time' => 'sometimes|date|after:now',
            'arrival_time_estimated' => 'nullable|date|after:departure_time',
            'available_seats' => 'sometimes|integer|min:1|max:8',
            'price_per_seat' => 'sometimes|numeric|min:0',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:ACTIVE,COMPLETED,CANCELLED',
        ]);
        
        // If cancelling, add cancellation reason
        if (isset($validated['status']) && $validated['status'] === 'CANCELLED') {
            $validated['cancelled_at'] = now();
            $validated['cancellation_reason'] = $request->cancellation_reason;
        }
        
        $ride->update($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Ride updated successfully',
            'data' => [
                'id' => $ride->id,
                'status' => $ride->status,
            ],
        ]);
    }

    /**
     * Remove the specified ride.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $ride = Ride::findOrFail($id);
        $user = $request->user();
        
        // Only the driver who created the ride or SuperAdmin can delete
        if ($ride->driver?->user_id !== $user->id && !$user->role->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this ride',
            ], 403);
        }
        
        // Cannot delete if ride has bookings
        if ($ride->bookings()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete ride with existing bookings. Cancel it instead.',
            ], 400);
        }
        
        $ride->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Ride deleted successfully',
        ]);
    }

    /**
     * Get rides for the current driver.
     */
    public function myRides(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isDriver()) {
            return response()->json([
                'success' => false,
                'message' => 'Only drivers can access this resource',
            ], 403);
        }
        
        $driver = $user->driver;
        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found',
            ], 404);
        }
        
        $rides = Ride::where('driver_id', $driver->id)
            ->orderBy('departure_time', 'desc')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $rides->map(fn($ride) => [
                'id' => $ride->id,
                'origin' => [
                    'address' => $ride->origin_address,
                ],
                'destination' => [
                    'address' => $ride->destination_address,
                ],
                'departure_time' => $ride->departure_time->toIso8601String(),
                'available_seats' => $ride->available_seats,
                'price_per_seat' => $ride->price_per_seat,
                'status' => $ride->status,
                'bookings_count' => $ride->bookings->count(),
            ]),
        ]);
    }

    /**
     * Book a ride (Passenger).
     * POST /api/v1/passenger/rides
     */
    public function bookRide(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only passengers can book rides
        if (!$user->isPassenger()) {
            return response()->json([
                'success' => false,
                'message' => 'Only passengers can book rides',
            ], 403);
        }

        $validated = $request->validate([
            'ride_id' => 'required|exists:rides,id',
            'seats' => 'required|integer|min:1|max:8',
            'pickup_address' => 'required|string',
            'dropoff_address' => 'required|string',
        ]);

        $ride = Ride::findOrFail($validated['ride_id']);

        // Check if ride is available
        if ($ride->status !== 'ACTIVE') {
            return response()->json([
                'success' => false,
                'message' => 'This ride is not available',
            ], 400);
        }

        // Check if enough seats available
        if ($ride->available_seats < $validated['seats']) {
            return response()->json([
                'success' => false,
                'message' => 'Not enough seats available',
            ], 400);
        }

        // Calculate price
        $totalPrice = $ride->price_per_seat * $validated['seats'];

        // Create booking
        $booking = \App\Models\Booking::create([
            'user_id' => $user->id,
            'ride_id' => $ride->id,
            'seats_booked' => $validated['seats'],
            'total_price' => $totalPrice,
            'pickup_address' => $validated['pickup_address'],
            'dropoff_address' => $validated['dropoff_address'],
            'status' => 'PENDING',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ride booked successfully',
            'data' => [
                'id' => $booking->id,
                'ride_id' => $booking->ride_id,
                'seats' => $booking->seats_booked,
                'total_price' => $booking->total_price,
                'status' => $booking->status,
                'hours_to_departure' => round(now()->diffInMinutes($ride->departure_time, false) / 60, 2),
                'travel_type' => now()->diffInHours($ride->departure_time, false) <= self::TICKET_THRESHOLD_HOURS ? 'TICKET' : 'BOOKING',
            ],
        ], 201);
    }

    /**
     * Show ride details for passenger.
     * GET /api/v1/passenger/rides/{id}
     */
    public function showRide(int $id): JsonResponse
    {
        $ride = Ride::with(['driver.user', 'vehicle'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $ride->id,
                'driver' => [
                    'id' => $ride->driver?->user?->id,
                    'name' => $ride->driver?->user?->name,
                    'phone' => $ride->driver?->user?->phone,
                    'rating' => $ride->driver?->rating,
                ],
                'vehicle' => [
                    'id' => $ride->vehicle?->id,
                    'make' => $ride->vehicle?->make,
                    'model' => $ride->vehicle?->model,
                    'color' => $ride->vehicle?->color,
                    'license_plate' => $ride->vehicle?->license_plate,
                ],
                'origin' => [
                    'address' => $ride->origin_address,
                    'lat' => $ride->origin_lat,
                    'lng' => $ride->origin_lng,
                ],
                'destination' => [
                    'address' => $ride->destination_address,
                    'lat' => $ride->destination_lat,
                    'lng' => $ride->destination_lng,
                ],
                'departure_time' => $ride->departure_time->toIso8601String(),
                'available_seats' => $ride->available_seats,
                'price_per_seat' => $ride->price_per_seat,
                'status' => $ride->status,
            ],
        ]);
    }

    /**
     * Cancel a ride booking.
     * PUT /api/v1/passenger/rides/{id}/cancel
     */
    public function cancelRide(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $booking = \App\Models\Booking::where('user_id', $user->id)
            ->where('ride_id', $id)
            ->firstOrFail();

        if ($booking->status === 'CANCELLED') {
            return response()->json([
                'success' => false,
                'message' => 'Booking is already cancelled',
            ], 400);
        }

        if ($booking->status === 'CONFIRMED') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel a confirmed booking',
            ], 400);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $booking->update([
            'status' => 'CANCELLED',
            'cancellation_reason' => $validated['reason'] ?? null,
            'cancelled_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Booking cancelled successfully',
            'data' => [
                'id' => $booking->id,
                'status' => $booking->status,
            ],
        ]);
    }

    /**
     * Get all rides (Admin).
     * GET /api/v1/admin/rides
     */
    public function adminRides(Request $request): JsonResponse
    {
        $query = Ride::with(['driver.user', 'vehicle']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date
        if ($request->has('date')) {
            $query->whereDate('departure_time', $request->date);
        }

        $rides = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $rides->map(fn($ride) => [
                'id' => $ride->id,
                'driver' => [
                    'id' => $ride->driver?->user?->id,
                    'name' => $ride->driver?->user?->name,
                ],
                'origin' => $ride->origin_address,
                'destination' => $ride->destination_address,
                'departure_time' => $ride->departure_time->toIso8601String(),
                'available_seats' => $ride->available_seats,
                'price_per_seat' => $ride->price_per_seat,
                'status' => $ride->status,
            ]),
            'pagination' => [
                'current_page' => $rides->currentPage(),
                'per_page' => $rides->perPage(),
                'total' => $rides->total(),
            ],
        ]);
    }

    /**
     * Get ride details (Admin).
     * GET /api/v1/admin/rides/{id}
     */
    public function adminRideDetail(int $id): JsonResponse
    {
        $ride = Ride::with(['driver.user', 'vehicle', 'bookings.user', 'reviews'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $ride->id,
                'driver' => [
                    'id' => $ride->driver?->user?->id,
                    'name' => $ride->driver?->user?->name,
                    'phone' => $ride->driver?->user?->phone,
                    'rating' => $ride->driver?->rating,
                ],
                'vehicle' => [
                    'id' => $ride->vehicle?->id,
                    'make' => $ride->vehicle?->make,
                    'model' => $ride->vehicle?->model,
                    'license_plate' => $ride->vehicle?->license_plate,
                ],
                'origin' => [
                    'address' => $ride->origin_address,
                    'lat' => $ride->origin_lat,
                    'lng' => $ride->origin_lng,
                ],
                'destination' => [
                    'address' => $ride->destination_address,
                    'lat' => $ride->destination_lat,
                    'lng' => $ride->destination_lng,
                ],
                'departure_time' => $ride->departure_time->toIso8601String(),
                'available_seats' => $ride->available_seats,
                'price_per_seat' => $ride->price_per_seat,
                'status' => $ride->status,
                'bookings_count' => $ride->bookings->count(),
                'reviews_count' => $ride->reviews->count(),
            ],
        ]);
    }
}
