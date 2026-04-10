<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\MobileUser;
use App\Models\Trip;
use App\Models\User;
use App\Services\MobileNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PassengerController extends Controller
{
    private const TICKET_THRESHOLD_HOURS = 6;

    public function __construct(private readonly MobileNotificationService $mobileNotificationService)
    {
    }

    /**
     * Get passenger profile.
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Check if user is a passenger
        if (!$user->isPassenger()) {
            return response()->json([
                'success' => false,
                'message' => 'Only passengers can access this resource',
            ], 403);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role->value,
                'is_approved' => $user->is_approved,
                'is_verified' => $user->is_verified,
                'created_at' => $user->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update passenger profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Check if user is a passenger
        if (!$user->isPassenger()) {
            return response()->json([
                'success' => false,
                'message' => 'Only passengers can access this resource',
            ], 403);
        }
        
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
        ]);
        
        $user->update($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
        ]);
    }

    /**
     * Get passenger statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Get booking stats
        $totalBookings = $user->bookings()->count();
        $completedBookings = $user->bookings()->where('status', 'COMPLETED')->count();
        $cancelledBookings = $user->bookings()->where('status', 'CANCELLED')->count();
        
        // Get trip stats
        $totalTrips = $user->tripsAsPassenger()->count();
        
        // Calculate total spent
        $totalSpent = $user->bookings()
            ->where('status', '!=', 'CANCELLED')
            ->sum('total_price');
        
        return response()->json([
            'success' => true,
            'data' => [
                'total_bookings' => $totalBookings,
                'completed_bookings' => $completedBookings,
                'cancelled_bookings' => $cancelledBookings,
                'total_trips' => $totalTrips,
                'total_spent' => $totalSpent,
            ],
        ]);
    }

    /**
     * Get passenger ride history.
     * GET /api/v1/passenger/rides/history
     */
    public function rideHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isPassenger()) {
            return response()->json([
                'success' => false,
                'message' => 'Only passengers can access ride history',
            ], 403);
        }
        
        $query = $user->bookings()
            ->with(['ride.driver.user', 'ride.vehicle'])
            ->orderBy('created_at', 'desc');
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }
        
        $perPage = $request->get('per_page', 15);
        $bookings = $query->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $bookings->map(fn($booking) => [
                'hours_to_departure' => $booking->ride?->departure_time
                    ? round(now()->diffInMinutes($booking->ride->departure_time, false) / 60, 2)
                    : null,
                'travel_type' => ($booking->ride?->departure_time && now()->diffInHours($booking->ride->departure_time, false) <= (int) config('ride.booking_to_trip_threshold_hours', self::TICKET_THRESHOLD_HOURS))
                    ? 'TRIP'
                    : 'BOOKING',
                'ticket_status' => ($booking->ride?->departure_time && now()->diffInHours($booking->ride->departure_time, false) <= (int) config('ride.booking_to_trip_threshold_hours', self::TICKET_THRESHOLD_HOURS))
                    ? match (strtolower((string) $booking->status)) {
                        'cancelled' => 'CANCELLED',
                        'completed' => 'USED',
                        'confirmed' => 'READY',
                        default => (now()->diffInMinutes($booking->ride->departure_time, false) < 0 ? 'EXPIRED' : 'PENDING'),
                    }
                    : null,
                'id' => $booking->id,
                'ride' => [
                    'id' => $booking->ride?->id,
                    'origin' => $booking->ride?->origin_address,
                    'destination' => $booking->ride?->destination_address,
                    'departure_time' => $booking->ride?->departure_time?->toIso8601String(),
                ],
                'driver' => [
                    'name' => $booking->ride?->driver?->user?->name,
                ],
                'seats_booked' => $booking->seats_booked,
                'total_price' => $booking->total_price,
                'status' => $booking->status,
                'booked_at' => $booking->created_at->toIso8601String(),
            ]),
            'pagination' => [
                'current_page' => $bookings->currentPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ],
        ]);
    }

    /**
     * List online drivers visible to passenger mobile app.
     * GET /api/v1/passenger/drivers/online
     */
    public function onlineDrivers(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isPassenger()) {
            return response()->json([
                'success' => false,
                'message' => 'Only passengers can access online drivers',
            ], 403);
        }

        $drivers = Driver::query()
            ->with('user:id,name,phone,is_approved')
            ->where('status', 'approved')
            ->where('availability_status', 'online')
            ->whereHas('user', fn ($query) => $query->where('is_approved', true))
            ->orderByDesc('last_online_at')
            ->limit((int) $request->integer('limit', 100))
            ->get();

        return response()->json([
            'success' => true,
            'data' => $drivers->map(fn (Driver $driver) => [
                'id' => $driver->id,
                'name' => $driver->user?->name,
                'phone' => $driver->user?->phone,
                'rating' => (float) $driver->rating,
                'total_rides' => (int) $driver->total_rides,
                'availability_status' => $driver->availability_status,
                'current_latitude' => $driver->current_latitude,
                'current_longitude' => $driver->current_longitude,
                'last_online_at' => $driver->last_online_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Passenger requests a ride directly from a selected online driver.
     * POST /api/v1/passenger/ride-requests
     */
    public function requestRide(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isPassenger()) {
            return response()->json([
                'success' => false,
                'message' => 'Only passengers can request rides',
            ], 403);
        }

        if (! $user->is_approved) {
            return response()->json([
                'success' => false,
                'message' => 'Your account must be approved to request rides',
            ], 403);
        }

        $validated = $request->validate([
            'driver_id' => 'required|integer|exists:drivers,id',
            'pickup_location' => 'required|string',
            'pickup_lat' => 'required|numeric|between:-90,90',
            'pickup_lng' => 'required|numeric|between:-180,180',
            'dropoff_location' => 'required|string',
            'dropoff_lat' => 'required|numeric|between:-90,90',
            'dropoff_lng' => 'required|numeric|between:-180,180',
            'fare' => 'required|numeric|min:0',
        ]);

        $driver = Driver::query()->with('user:id,is_approved')->findOrFail((int) $validated['driver_id']);

        if (($driver->availability_status ?? 'offline') !== 'online') {
            return response()->json([
                'success' => false,
                'message' => 'Selected driver is currently offline',
            ], 400);
        }

        if (! $driver->user?->is_approved) {
            return response()->json([
                'success' => false,
                'message' => 'Selected driver is not approved',
            ], 400);
        }

        $passengerMobileUserId = $this->resolvePassengerMobileUserId($user);

        $trip = Trip::create([
            ...$validated,
            'passenger_id' => $passengerMobileUserId,
            'driver_id' => $driver->id,
            'status' => 'PENDING',
            'requested_at' => now(),
        ]);

        $this->mobileNotificationService->sendRideRequestToDriver($trip, $driver);

        return response()->json([
            'success' => true,
            'message' => 'Ride request sent to driver',
            'data' => [
                'trip_id' => $trip->id,
                'status' => $trip->status,
                'driver_id' => $driver->id,
                'requested_at' => $trip->requested_at?->toIso8601String(),
            ],
        ], 201);
    }

    private function resolvePassengerMobileUserId(User $user): int
    {
        if ($user->mobile_user_id) {
            return (int) $user->mobile_user_id;
        }

        $mobileUserId = MobileUser::query()
            ->where('email', $user->email)
            ->value('id');

        if ($mobileUserId) {
            return (int) $mobileUserId;
        }

        throw ValidationException::withMessages([
            'user' => 'Passenger mobile profile is not linked. Please contact support.',
        ]);
    }
}
