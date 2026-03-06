<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PassengerController extends Controller
{
    private const TICKET_THRESHOLD_HOURS = 6;

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
                'travel_type' => ($booking->ride?->departure_time && now()->diffInHours($booking->ride->departure_time, false) <= self::TICKET_THRESHOLD_HOURS)
                    ? 'TICKET'
                    : 'BOOKING',
                'ticket_status' => ($booking->ride?->departure_time && now()->diffInHours($booking->ride->departure_time, false) <= self::TICKET_THRESHOLD_HOURS)
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
}
