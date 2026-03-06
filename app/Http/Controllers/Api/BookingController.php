<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Ride;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    private const TICKET_THRESHOLD_HOURS = 6;

    /**
     * Display a listing of bookings.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = Booking::with(['ride.driver.user', 'user', 'payment']);
        
        // Role-based filtering
        if ($user->role->isSuperAdmin() || $user->role->isManager()) {
            // Admins can see all bookings
        } else {
            // Regular users can only see their own bookings
            $query->where('user_id', $user->id);
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        $bookings = $query->orderBy('created_at', 'desc')->get();

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
                'user' => [
                    'id' => $booking->user->id,
                    'name' => $booking->user->name,
                ],
                'ride' => [
                    'id' => $booking->ride->id,
                    'origin' => $booking->ride->origin_address,
                    'destination' => $booking->ride->destination_address,
                    'departure_time' => $booking->ride->departure_time->toIso8601String(),
                    'driver' => [
                        'name' => $booking->ride->driver?->user?->name,
                    ],
                ],
                'seats_booked' => $booking->seats_booked,
                'total_price' => $booking->total_price,
                'currency' => $booking->currency,
                'status' => $booking->status,
                'pickup_address' => $booking->pickup_address,
                'dropoff_address' => $booking->dropoff_address,
                'confirmed_at' => $booking->confirmed_at?->toIso8601String(),
                'cancelled_at' => $booking->cancelled_at?->toIso8601String(),
                'created_at' => $booking->created_at->toIso8601String(),
                'hours_to_departure' => $this->hoursToDeparture($booking),
                'travel_type' => $this->resolveTravelType($booking),
                'ticket_status' => $this->resolveTicketStatus($booking),
            ]),
        ]);
    }

    /**
     * Display the specified booking.
     */
    public function show(int $id): JsonResponse
    {
        $booking = Booking::with(['ride.driver.user', 'ride.vehicle', 'user', 'payment', 'review'])->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $booking->id,
                'user' => [
                    'id' => $booking->user->id,
                    'name' => $booking->user->name,
                    'email' => $booking->user->email,
                    'phone' => $booking->user->phone,
                ],
                'ride' => [
                    'id' => $booking->ride->id,
                    'origin' => [
                        'address' => $booking->ride->origin_address,
                        'lat' => $booking->ride->origin_lat,
                        'lng' => $booking->ride->origin_lng,
                    ],
                    'destination' => [
                        'address' => $booking->ride->destination_address,
                        'lat' => $booking->ride->destination_lat,
                        'lng' => $booking->ride->destination_lng,
                    ],
                    'departure_time' => $booking->ride->departure_time->toIso8601String(),
                    'driver' => [
                        'id' => $booking->ride->driver?->user?->id,
                        'name' => $booking->ride->driver?->user?->name,
                        'phone' => $booking->ride->driver?->user?->phone,
                    ],
                    'vehicle' => [
                        'make' => $booking->ride->vehicle?->make,
                        'model' => $booking->ride->vehicle?->model,
                        'color' => $booking->ride->vehicle?->color,
                        'license_plate' => $booking->ride->vehicle?->license_plate,
                    ],
                ],
                'seats_booked' => $booking->seats_booked,
                'total_price' => $booking->total_price,
                'currency' => $booking->currency,
                'status' => $booking->status,
                'pickup_address' => $booking->pickup_address,
                'pickup_lat' => $booking->pickup_lat,
                'pickup_lng' => $booking->pickup_lng,
                'dropoff_address' => $booking->dropoff_address,
                'dropoff_lat' => $booking->dropoff_lat,
                'dropoff_lng' => $booking->dropoff_lng,
                'special_requests' => $booking->special_requests,
                'confirmed_at' => $booking->confirmed_at?->toIso8601String(),
                'cancelled_at' => $booking->cancelled_at?->toIso8601String(),
                'cancellation_reason' => $booking->cancellation_reason,
                'payment' => $booking->payment ? [
                    'id' => $booking->payment->id,
                    'amount' => $booking->payment->amount,
                    'status' => $booking->payment->status,
                    'payment_method' => $booking->payment->payment_method,
                ] : null,
                'review' => $booking->review ? [
                    'id' => $booking->review->id,
                    'rating' => $booking->review->rating,
                    'comment' => $booking->review->comment,
                ] : null,
                'created_at' => $booking->created_at->toIso8601String(),
                'hours_to_departure' => $this->hoursToDeparture($booking),
                'travel_type' => $this->resolveTravelType($booking),
                'ticket_status' => $this->resolveTicketStatus($booking),
            ],
        ]);
    }

    /**
     * Create a new booking.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Check if user is approved
        if (!$user->is_approved) {
            return response()->json([
                'success' => false,
                'message' => 'Your account must be approved to book rides',
            ], 403);
        }
        
        $validated = $request->validate([
            'ride_id' => 'required|exists:rides,id',
            'seats_booked' => 'required|integer|min:1|max:8',
            'pickup_address' => 'required|string',
            'pickup_lat' => 'required|numeric|between:-90,90',
            'pickup_lng' => 'required|numeric|between:-180,180',
            'dropoff_address' => 'required|string',
            'dropoff_lat' => 'required|numeric|between:-90,90',
            'dropoff_lng' => 'required|numeric|between:-180,180',
            'special_requests' => 'nullable|string',
        ]);
        
        // Get the ride
        $ride = Ride::findOrFail($validated['ride_id']);
        
        // Check if ride is available
        if ($ride->status !== 'ACTIVE') {
            return response()->json([
                'success' => false,
                'message' => 'This ride is not available for booking',
            ], 400);
        }
        
        // Check if ride has departed
        if ($ride->departure_time <= now()) {
            return response()->json([
                'success' => false,
                'message' => 'This ride has already departed',
            ], 400);
        }
        
        // Check available seats
        if ($ride->available_seats < $validated['seats_booked']) {
            return response()->json([
                'success' => false,
                'message' => 'Not enough available seats',
            ], 400);
        }
        
        // Calculate total price
        $totalPrice = $ride->price_per_seat * $validated['seats_booked'];
        
        // Create booking
        $booking = Booking::create([
            'user_id' => $user->id,
            'ride_id' => $ride->id,
            'seats_booked' => $validated['seats_booked'],
            'total_price' => $totalPrice,
            'currency' => $ride->currency,
            'status' => 'PENDING',
            'pickup_address' => $validated['pickup_address'],
            'pickup_lat' => $validated['pickup_lat'],
            'pickup_lng' => $validated['pickup_lng'],
            'dropoff_address' => $validated['dropoff_address'],
            'dropoff_lat' => $validated['dropoff_lat'],
            'dropoff_lng' => $validated['dropoff_lng'],
            'special_requests' => $validated['special_requests'] ?? null,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Booking created successfully',
            'data' => [
                'id' => $booking->id,
                'status' => $booking->status,
                'total_price' => $booking->total_price,
                'currency' => $booking->currency,
                'hours_to_departure' => $this->hoursToDeparture($booking->loadMissing('ride')),
                'travel_type' => $this->resolveTravelType($booking),
                'ticket_status' => $this->resolveTicketStatus($booking),
            ],
        ], 201);
    }

    /**
     * Update the specified booking.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $booking = Booking::findOrFail($id);
        $user = $request->user();
        
        // Only the booking owner can update (for now, just status changes)
        if ($booking->user_id !== $user->id && !$user->role->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this booking',
            ], 403);
        }
        
        // Cannot update if already confirmed or cancelled
        if (in_array($booking->status, ['CONFIRMED', 'COMPLETED', 'CANCELLED'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update booking that is already confirmed, completed, or cancelled',
            ], 400);
        }
        
        $validated = $request->validate([
            'seats_booked' => 'sometimes|integer|min:1|max:8',
            'pickup_address' => 'sometimes|string',
            'pickup_lat' => 'sometimes|numeric|between:-90,90',
            'pickup_lng' => 'sometimes|numeric|between:-180,180',
            'dropoff_address' => 'sometimes|string',
            'dropoff_lat' => 'sometimes|numeric|between:-90,90',
            'dropoff_lng' => 'sometimes|numeric|between:-180,180',
            'special_requests' => 'nullable|string',
        ]);
        
        // Recalculate price if seats changed
        if (isset($validated['seats_booked']) && $validated['seats_booked'] !== $booking->seats_booked) {
            $ride = $booking->ride;
            $validated['total_price'] = $ride->price_per_seat * $validated['seats_booked'];
        }
        
        $booking->update($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Booking updated successfully',
            'data' => [
                'id' => $booking->id,
                'status' => $booking->status,
                'seats_booked' => $booking->seats_booked,
                'total_price' => $booking->total_price,
            ],
        ]);
    }

    /**
     * Cancel the specified booking.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $booking = Booking::findOrFail($id);
        $user = $request->user();
        
        // Only the booking owner or SuperAdmin can cancel
        if ($booking->user_id !== $user->id && !$user->role->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to cancel this booking',
            ], 403);
        }
        
        // Cannot cancel if already cancelled or completed
        if (in_array($booking->status, ['CANCELLED', 'COMPLETED'])) {
            return response()->json([
                'success' => false,
                'message' => 'Booking is already cancelled or completed',
            ], 400);
        }
        
        $request->validate([
            'cancellation_reason' => 'nullable|string',
        ]);
        
        $booking->update([
            'status' => 'CANCELLED',
            'cancelled_at' => now(),
            'cancellation_reason' => $request->cancellation_reason,
        ]);
        
        // Restore available seats on the ride
        $ride = $booking->ride;
        $ride->increment('available_seats', $booking->seats_booked);
        
        return response()->json([
            'success' => true,
            'message' => 'Booking cancelled successfully',
        ]);
    }

    /**
     * Get current user's bookings.
     */
    public function myBookings(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = Booking::with(['ride.driver.user', 'payment'])
            ->where('user_id', $user->id);
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        $bookings = $query->orderBy('created_at', 'desc')->get();

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
                    'driver' => [
                        'name' => $booking->ride->driver?->user?->name,
                    ],
                ],
                'seats_booked' => $booking->seats_booked,
                'total_price' => $booking->total_price,
                'status' => $booking->status,
                'confirmed_at' => $booking->confirmed_at?->toIso8601String(),
                'cancelled_at' => $booking->cancelled_at?->toIso8601String(),
                'created_at' => $booking->created_at->toIso8601String(),
                'hours_to_departure' => $this->hoursToDeparture($booking),
                'travel_type' => $this->resolveTravelType($booking),
                'ticket_status' => $this->resolveTicketStatus($booking),
            ]),
        ]);
    }

    /**
     * Confirm a booking (Driver only).
     */
    public function confirm(int $id): JsonResponse
    {
        $booking = Booking::findOrFail($id);
        $user = request()->user();
        
        // Only the driver who owns the ride can confirm
        if ($booking->ride->driver?->user_id !== $user->id && !$user->role->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only the ride driver can confirm bookings',
            ], 403);
        }
        
        if ($booking->status !== 'PENDING') {
            return response()->json([
                'success' => false,
                'message' => 'Booking is not pending',
            ], 400);
        }
        
        $booking->update([
            'status' => 'CONFIRMED',
            'confirmed_at' => now(),
        ]);
        
        // Reduce available seats
        $booking->ride->decrement('available_seats', $booking->seats_booked);
        
        return response()->json([
            'success' => true,
            'message' => 'Booking confirmed successfully',
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
}
