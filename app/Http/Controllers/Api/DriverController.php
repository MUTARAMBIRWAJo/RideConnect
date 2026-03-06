<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\Ride;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    private const TICKET_THRESHOLD_HOURS = 6;

    /**
     * Get driver profile.
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Check if user is a driver
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
                'driver' => [
                    'id' => $driver->id,
                    'license_number' => $driver->license_number,
                    'rating' => $driver->rating,
                    'total_rides' => $driver->total_rides,
                ],
                'created_at' => $user->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update driver profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Check if user is a driver
        if (!$user->isDriver()) {
            return response()->json([
                'success' => false,
                'message' => 'Only drivers can access this resource',
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
     * Get driver statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Check if user is a driver
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
        
        // Get ride stats
        $totalRides = Ride::where('driver_id', $driver->id)->count();
        $activeRides = Ride::where('driver_id', $driver->id)
            ->where('status', 'ACTIVE')
            ->count();
        
        // Get booking stats for driver's rides
        $rideIds = Ride::where('driver_id', $driver->id)->pluck('id');
        $bookingsQuery = Booking::whereIn('ride_id', $rideIds)->whereHas('ride');

        if ($request->filled('type')) {
            $requestedType = strtolower((string) $request->string('type'));
            $threshold = now()->copy()->addHours(self::TICKET_THRESHOLD_HOURS);

            if ($requestedType === 'ticket') {
                $bookingsQuery->whereHas('ride', fn ($rideQuery) => $rideQuery->where('departure_time', '<=', $threshold));
            }

            if ($requestedType === 'booking') {
                $bookingsQuery->whereHas('ride', fn ($rideQuery) => $rideQuery->where('departure_time', '>', $threshold));
            }
        }

        $totalBookings = (clone $bookingsQuery)->count();
        $completedBookings = (clone $bookingsQuery)
            ->where('status', 'CONFIRMED')
            ->count();
        
        // Get trip stats
        $totalTrips = Trip::where('driver_id', $driver->id)->count();
        $completedTrips = Trip::where('driver_id', $driver->id)
            ->where('status', 'COMPLETED')
            ->count();
        
        // Calculate total earnings
        $totalEarnings = (clone $bookingsQuery)
            ->where('status', 'CONFIRMED')
            ->sum('total_price');
        
        return response()->json([
            'success' => true,
            'data' => [
                'total_rides' => $totalRides,
                'active_rides' => $activeRides,
                'total_bookings' => $totalBookings,
                'completed_bookings' => $completedBookings,
                'total_trips' => $totalTrips,
                'completed_trips' => $completedTrips,
                'total_earnings' => $totalEarnings,
                'rating' => $driver->rating,
                'total_rides_completed' => $driver->total_rides,
                'type_filter' => $request->filled('type') ? strtoupper((string) $request->string('type')) : null,
            ],
        ]);
    }

    /**
     * Get driver's bookings.
     */
    public function bookings(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Check if user is a driver
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
        
        $rideIds = Ride::where('driver_id', $driver->id)->pluck('id');
        
        $query = Booking::whereIn('ride_id', $rideIds);
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        $bookings = $query->with(['ride', 'user'])->orderBy('created_at', 'desc')->get();

        if ($request->filled('type')) {
            $requestedType = strtoupper((string) $request->string('type'));
            $bookings = $bookings->filter(function (Booking $booking) use ($requestedType): bool {
                return $this->resolveTravelType($booking) === $requestedType;
            })->values();
        }
        
        return response()->json([
            'success' => true,
            'data' => $bookings->map(fn($booking) => [
                'id' => $booking->id,
                'ride' => [
                    'id' => $booking->ride->id,
                    'origin' => $booking->ride->origin_address,
                    'destination' => $booking->ride->destination_address,
                    'departure_time' => $booking->ride->departure_time->toIso8601String(),
                ],
                'passenger' => [
                    'id' => $booking->user->id,
                    'name' => $booking->user->name,
                    'phone' => $booking->user->phone,
                ],
                'seats_booked' => $booking->seats_booked,
                'total_price' => $booking->total_price,
                'status' => $booking->status,
                'pickup_address' => $booking->pickup_address,
                'dropoff_address' => $booking->dropoff_address,
                'hours_to_departure' => $this->hoursToDeparture($booking),
                'travel_type' => $this->resolveTravelType($booking),
                'ticket_status' => $this->resolveTicketStatus($booking),
            ]),
        ]);
    }

    private function hoursToDeparture(Booking $booking): ?float
    {
        $departure = $booking->ride?->departure_time;

        if (! $departure) {
            return null;
        }

        return round(now()->diffInMinutes($departure, false) / 60, 2);
    }

    private function resolveTravelType(Booking $booking): string
    {
        $hoursToDeparture = $this->hoursToDeparture($booking);

        if ($hoursToDeparture === null) {
            return 'BOOKING';
        }

        return $hoursToDeparture <= self::TICKET_THRESHOLD_HOURS ? 'TICKET' : 'BOOKING';
    }

    private function resolveTicketStatus(Booking $booking): ?string
    {
        if ($this->resolveTravelType($booking) !== 'TICKET') {
            return null;
        }

        $normalizedStatus = strtolower((string) $booking->status);
        $hoursToDeparture = $this->hoursToDeparture($booking);

        if ($normalizedStatus === 'cancelled') {
            return 'CANCELLED';
        }

        if ($normalizedStatus === 'completed') {
            return 'USED';
        }

        if ($hoursToDeparture !== null && $hoursToDeparture < 0) {
            return 'EXPIRED';
        }

        if ($normalizedStatus === 'confirmed') {
            return 'READY';
        }

        return 'PENDING';
    }

    /**
     * Get driver's trips.
     */
    public function myTrips(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Check if user is a driver
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
        
        $query = Trip::where('driver_id', $driver->id);
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        $trips = $query->with(['passenger.user'])->orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'data' => $trips->map(fn($trip) => [
                'id' => $trip->id,
                'passenger' => [
                    'id' => $trip->passenger?->user?->id,
                    'name' => $trip->passenger?->user?->name,
                    'phone' => $trip->passenger?->user?->phone,
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
}
