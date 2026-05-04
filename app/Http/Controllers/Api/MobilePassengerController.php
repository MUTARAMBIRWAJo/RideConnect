<?php

namespace App\Http\Controllers\Api;

use App\Domain\Core\DomainGuard;
use App\Domain\Ride\RidePolicy;
use App\Domain\Trip\TripStateMachine;
use App\Events\Domain\TripMatched;
use App\Exceptions\DomainException;
use App\Models\Booking;
use App\Models\Ride;
use App\Models\Trip;
use App\Services\DriverAssignmentService;
use App\Services\Location\TripLocationService;
use App\Services\MobileNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;

/**
 * MobilePassengerController handles mobile app APIs for passengers.
 *
 * Focused on Flutter mobile app integration with standardized responses.
 */
class MobilePassengerController extends Controller
{
    public function __construct(
        private readonly MobileNotificationService $notificationService,
        private readonly TripLocationService $tripLocationService,
        private readonly DriverAssignmentService $driverAssignmentService,
    ) {}

    /**
     * GET /api/mobile/rides
     * Fetch available rides for mobile app.
     */
    public function getRides(Request $request): JsonResponse
    {
        $query = Ride::query()
            ->with(['driver.vehicles', 'zone', 'corridor'])
            ->where('status', 'published');

        // Filter by transport type
        if ($request->has('transport_type')) {
            $query->where('transport_type', $request->transport_type);
        }

        // Filter by travel mode
        if ($request->has('travel_mode')) {
            $query->where('travel_mode', $request->travel_mode);
        }

        // Available only filter
        if ($request->boolean('available_only', true)) {
            $query->where('available_seats', '>', 0);
        }

        $rides = $query->orderBy('departure_time')->get();

        $data = $rides->map(function ($ride) {
            return [
                'id' => $ride->id,
                'transport_type' => $ride->transport_type,
                'travel_mode' => $ride->travel_mode,
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
                'departure_time' => $ride->departure_time?->toIso8601String(),
                'available_seats' => $ride->available_seats,
                'price' => $ride->price_per_seat,
                'currency' => 'RWF',
                'ride_rules' => RidePolicy::toApiRules($ride),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * POST /api/mobile/bookings
     * Create booking for SCHEDULED rides only.
     */
    public function createBooking(Request $request): JsonResponse
    {
        DomainGuard::assertUsingPolicy(__METHOD__);

        $user = $request->user();

        if (!$user->is_approved) {
            return response()->json([
                'status' => 'error',
                'type' => 'DOMAIN_ERROR',
                'message' => 'Account not approved',
                'code' => 403,
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'ride_id' => 'required|exists:rides,id',
            'seats' => 'required|integer|min:1|max:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $ride = Ride::findOrFail($validated['ride_id']);

        try {
            RidePolicy::assertBookingAllowed($ride);
        } catch (DomainException $e) {
            return response()->json([
                'status' => 'error',
                'type' => 'DOMAIN_ERROR',
                'message' => $e->getMessage(),
                'code' => 422,
            ], 422);
        }

        // Create booking with seat locking
        $booking = Booking::create([
            'user_id' => $user->id,
            'ride_id' => $ride->id,
            'seats_booked' => $validated['seats'],
            'total_price' => $validated['seats'] * ($ride->price_per_seat ?? 0),
            'status' => 'PENDING',
            'currency' => 'RWF',
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $booking->id,
                'status' => $booking->status,
                'total_price' => $booking->total_price,
                'currency' => $booking->currency,
                'hours_to_departure' => $ride->departure_time?->diffInHours(now(), false),
            ],
        ], 201);
    }

    /**
     * POST /api/mobile/trips/request
     * Create direct trip request (ON_DEMAND).
     */
    public function requestTrip(Request $request): JsonResponse
    {
        DomainGuard::assertUsingPolicy(__METHOD__);

        $user = $request->user();

        if (!$user->is_approved) {
            return response()->json([
                'status' => 'error',
                'type' => 'DOMAIN_ERROR',
                'message' => 'Account not approved',
                'code' => 403,
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'ride_id' => 'nullable|exists:rides,id',
            'pickup_location' => 'required|string|min:3',
            'pickup_lat' => 'required|numeric|between:-90,90',
            'pickup_lng' => 'required|numeric|between:-180,180',
            'dropoff_location' => 'required|string|min:3',
            'dropoff_lat' => 'required|numeric|between:-90,90',
            'dropoff_lng' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pickup and dropoff locations are required',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $passengerMobileUserId = $this->resolvePassengerMobileUserId($user);

        $ride = null;
        if ($validated['ride_id']) {
            $ride = Ride::query()->with('driver.vehicles')->findOrFail((int) $validated['ride_id']);

            try {
                RidePolicy::assertTripAllowed($ride);
            } catch (DomainException $e) {
                return response()->json([
                    'status' => 'error',
                    'type' => 'DOMAIN_ERROR',
                    'message' => $e->getMessage(),
                    'code' => 422,
                ], 422);
            }
        }

        // Create trip
        $trip = new Trip([
            'ride_id' => $validated['ride_id'] ?? null,
            'passenger_id' => $passengerMobileUserId,
            'pickup_location' => $validated['pickup_location'],
            'pickup_lat' => $validated['pickup_lat'],
            'pickup_lng' => $validated['pickup_lng'],
            'dropoff_location' => $validated['dropoff_location'],
            'dropoff_lat' => $validated['dropoff_lat'],
            'dropoff_lng' => $validated['dropoff_lng'],
            'fare' => 0, // Will be calculated
            'status' => 'PENDING',
            'requested_at' => now(),
        ]);

        $trip->validateForExecution();
        $trip->save();

        // Auto-assign driver if ride specified
        if ($ride) {
            $assignedTrip = $this->driverAssignmentService->autoAssign($trip, $ride);
            if ($assignedTrip) {
                event(new TripMatched($assignedTrip->id, $assignedTrip->driver_id));
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $trip->id,
                'trip_state' => $trip->status,
                'driver_id' => $trip->driver_id,
            ],
        ], 201);
    }

    /**
     * GET /api/mobile/trips/current
     * Get passenger's current active trip.
     */
    public function getCurrentTrip(Request $request): JsonResponse
    {
        $user = $request->user();
        $passengerMobileUserId = $this->resolvePassengerMobileUserId($user);

        $trip = Trip::query()
            ->where('passenger_id', $passengerMobileUserId)
            ->whereIn('status', ['PENDING', 'ACCEPTED', 'STARTED'])
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$trip) {
            return response()->json([
                'status' => 'success',
                'data' => null,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'trip_id' => $trip->id,
                'trip_state' => $trip->status,
                'driver' => $trip->driver ? [
                    'id' => $trip->driver->id,
                    'name' => $trip->driver->user->name ?? 'Unknown',
                ] : null,
                'vehicle' => $trip->driver?->vehicles->first() ? [
                    'make' => $trip->driver->vehicles->first()->make,
                    'model' => $trip->driver->vehicles->first()->model,
                    'color' => $trip->driver->vehicles->first()->color,
                ] : null,
                'driver_location' => $this->tripLocationService->getCurrentLocation($trip),
                'eta' => 12, // Placeholder
                'fare' => $trip->fare,
            ],
        ]);
    }

    /**
     * GET /api/mobile/trips/{id}/track
     * Track driver location for trip.
     */
    public function trackTrip(int $id): JsonResponse
    {
        $user = request()->user();
        $passengerMobileUserId = $this->resolvePassengerMobileUserId($user);

        $trip = Trip::query()
            ->where('id', $id)
            ->where('passenger_id', $passengerMobileUserId)
            ->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => [
                'driver_location' => $this->tripLocationService->getCurrentLocation($trip),
                'route_path' => [], // Placeholder for route coordinates
                'eta' => 12, // Placeholder
            ],
        ]);
    }

    /**
     * PUT /api/mobile/trips/{id}/cancel
     * Cancel trip (passenger action).
     */
    public function cancelTrip(int $id): JsonResponse
    {
        $user = request()->user();
        $passengerMobileUserId = $this->resolvePassengerMobileUserId($user);

        $trip = Trip::query()
            ->where('id', $id)
            ->where('passenger_id', $passengerMobileUserId)
            ->firstOrFail();

        try {
            TripStateMachine::assertTransitionForTrip($trip, TripStateMachine::CANCELLED);
            $trip->status = TripStateMachine::CANCELLED;
            $trip->save();
        } catch (DomainException $e) {
            return response()->json([
                'status' => 'error',
                'type' => 'DOMAIN_ERROR',
                'message' => $e->getMessage(),
                'code' => 422,
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'trip_id' => $trip->id,
                'trip_state' => $trip->status,
            ],
        ]);
    }

    /**
     * PUT /api/mobile/trips/{id}/complete
     * Confirm trip completion (passenger action).
     */
    public function completeTrip(int $id): JsonResponse
    {
        $user = request()->user();
        $passengerMobileUserId = $this->resolvePassengerMobileUserId($user);

        $trip = Trip::query()
            ->where('id', $id)
            ->where('passenger_id', $passengerMobileUserId)
            ->firstOrFail();

        try {
            TripStateMachine::assertTransitionForTrip($trip, TripStateMachine::COMPLETED);
            $trip->status = TripStateMachine::COMPLETED;
            $trip->save();
        } catch (DomainException $e) {
            return response()->json([
                'status' => 'error',
                'type' => 'DOMAIN_ERROR',
                'message' => $e->getMessage(),
                'code' => 422,
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'trip_id' => $trip->id,
                'trip_state' => $trip->status,
            ],
        ]);
    }

    private function resolvePassengerMobileUserId($user): int
    {
        return $user->mobile_user_id ?? throw new \Exception('Mobile user ID required');
    }
}