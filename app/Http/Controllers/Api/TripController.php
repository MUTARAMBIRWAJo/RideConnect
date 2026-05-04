<?php

namespace App\Http\Controllers\Api;

use App\Domain\Core\DomainGuard;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\MobileUser;
use App\Models\Trip;
use App\Models\Ride;
use App\Domain\Ride\RidePolicy;
use App\Domain\Driver\DriverPolicy;
use App\Domain\Trip\TripStateMachine;
use App\Events\Domain\TripMatched;
use App\Events\Domain\TripStarted;
use App\Events\Domain\TripCompleted;
use App\Exceptions\DomainException;
use App\Services\AITrainingDataLogger;
use App\Services\Location\TripLocationService;
use App\Services\MobileNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * TripController handles the complete trip lifecycle.
 *
 * CORE DOMAIN RULE: Trip = Execution (real movement happening now)
 *
 * ========== TRIP CREATION RULES ==========
 *
 * ✅ BUS (SCHEDULED) → Booking → Trip
 *    POST /api/passenger/trips/create-from-booking
 *    Passengers book, then convert booking to trip
 *
 * ✅ CAR (SCHEDULED) → Booking → Trip
 *    POST /api/passenger/trips/create-from-booking
 *    Same as BUS
 *
 * ✅ CAR (ON_DEMAND) → Trip
 *    POST /api/passenger/trips
 *    Direct trip request, no booking
 *
 * ✅ MOTORCYCLE (ON_DEMAND) → Trip
 *    POST /api/passenger/trips
 *    Direct trip request, no booking
 *
 * ========== CRITICAL VALIDATIONS ==========
 *
 * ❌ BUS trips can ONLY come from booking (never direct POST /trips)
 * ❌ SCHEDULED rides can ONLY come from booking (never direct POST /trips)
 * ❌ Trip MUST have pickup_location and dropoff_location (never null)
 * ❌ Trip MUST have pickup/dropoff coordinates (never null)
 * ❌ Only ONE way to create trip per ride type (enforce in controller)
 *
 * ========== STATE MACHINE ==========
 *
 * Passenger: PENDING → (driver accepts) → ACCEPTED → STARTED → COMPLETED
 * Driver:    Can accept, start, complete, or cancel
 *
 * @see RidePolicy - Authoritative source of business rules
 * @see Trip - Model with mandatory field validation
 */
class TripController extends Controller
{
    public function __construct(
        private readonly MobileNotificationService $mobileNotificationService,
        private readonly TripLocationService $tripLocationService,
        private readonly DriverAssignmentService $driverAssignmentService,
    ) {
    }

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
                if ($user->mobile_user_id) {
                    $q->where('passenger_id', (int) $user->mobile_user_id);
                }

                if ($user->driver?->id) {
                    $q->orWhere('driver_id', (int) $user->driver->id);
                }
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
                'booking_id' => $trip->booking_id,
                'ride_id' => $trip->ride_id,
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
                'trip_state' => $trip->status,
                'driver_location' => $this->tripLocationService->getCurrentLocation($trip),
                'eta' => 12,
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
        $user = request()->user();

        if (! $user->role->isSuperAdmin() && ! $user->role->isManager()) {
            $isPassenger = $user->mobile_user_id && (int) $trip->passenger_id === (int) $user->mobile_user_id;
            $isDriver = $user->driver?->id && (int) $trip->driver_id === (int) $user->driver->id;

            if (! $isPassenger && ! $isDriver) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this trip',
                ], 403);
            }
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $trip->id,
                'booking_id' => $trip->booking_id,
                'ride_id' => $trip->ride_id,
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
                'trip_state' => $trip->status,
                'driver_location' => $this->tripLocationService->getCurrentLocation($trip),
                'eta' => 12,
                'requested_at' => $trip->requested_at?->toIso8601String(),
                'started_at' => $trip->started_at?->toIso8601String(),
                'completed_at' => $trip->completed_at?->toIso8601String(),
                'created_at' => $trip->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Create a direct trip request (ON_DEMAND only).
     * For SCHEDULED rides or BUS, use createFromBooking instead.
     */
    public function store(Request $request): JsonResponse
    {
        DomainGuard::assertUsingPolicy(__METHOD__);

        $user = $request->user();
        
        // Check if user is approved
        if (!$user->is_approved) {
            return response()->json([
                'success' => false,
                'message' => 'Your account must be approved to request trips',
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'ride_id' => 'required|exists:rides,id',
            'pickup_location' => 'required|string|min:3',
            'pickup_lat' => 'required|numeric|between:-90,90',
            'pickup_lng' => 'required|numeric|between:-180,180',
            'dropoff_location' => 'required|string|min:3',
            'dropoff_lat' => 'required|numeric|between:-90,90',
            'dropoff_lng' => 'required|numeric|between:-180,180',
            'fare' => 'required|numeric|min:0',
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

        $ride = Ride::query()->with('driver.vehicles')->findOrFail((int) $validated['ride_id']);

        // ❌ Enforce: BUS trips MUST come from booking only
        if ($ride->isBus()) {
            return response()->json([
                'success' => false,
                'message' => 'BUS routes require booking. Use POST /api/passenger/trips/create-from-booking',
                'error_code' => 'BUS_REQUIRES_BOOKING',
            ], 422);
        }

        // ❌ Enforce: SCHEDULED rides must use booking flow
        if ($ride->isScheduled()) {
            return response()->json([
                'success' => false,
                'message' => 'SCHEDULED rides require booking. Use POST /api/passenger/trips/create-from-booking',
                'error_code' => 'SCHEDULED_REQUIRES_BOOKING',
            ], 422);
        }

        try {
            // Enforce transport rules first
            RidePolicy::assertTransportRules($ride);
            RidePolicy::assertTripAllowed($ride);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ], 422);
        }

        if (! $ride->driver) {
            return response()->json([
                'status' => 'error',
                'message' => 'No active driver available for this ride',
            ], 422);
        }

        if (strtolower((string) $ride->driver->status) !== 'active') {
            return response()->json([
                'status' => 'error',
                'message' => 'Assigned ride driver is not active',
            ], 422);
        }

        $activeVehicle = $ride->driver->vehicles->first(fn ($vehicle) => $vehicle->is_active);
        if (! $activeVehicle) {
            return response()->json([
                'status' => 'error',
                'message' => 'No active vehicle is available for the selected ride',
            ], 422);
        }

        // Create trip in PENDING state first
        $trip = new Trip([
            ...$validated,
            'passenger_id' => $passengerMobileUserId,
            'driver_id' => null,  // Will be assigned below
            'status' => 'PENDING',
            'requested_at' => now(),
        ]);
        $trip->validateForExecution();
        $trip->save();

        // Auto-assign best available driver
        $assignedTrip = $this->driverAssignmentService->autoAssign($trip, $ride);
        
        if (! $assignedTrip) {
            // No driver available - keep trip in PENDING, driver will be found later
            $assignedTrip = $trip;
        }

        app(AITrainingDataLogger::class)->logRideRequest($assignedTrip);
        
        return response()->json([
            'success' => true,
            'message' => 'Trip request created successfully',
            'data' => [
                'id' => $assignedTrip->id,
                'booking_id' => $assignedTrip->booking_id,
                'ride_id' => $assignedTrip->ride_id,
                'driver_id' => $assignedTrip->driver_id,
                'status' => $assignedTrip->status,
                'trip_state' => $assignedTrip->status,
                'driver_location' => $this->tripLocationService->getCurrentLocation($assignedTrip),
                'eta' => 12,
            ],
        ], 201);
    }

    /**
     * Create a trip from booking (SCHEDULED rides only).
     * Passengers can convert their own SCHEDULED bookings to trips.
     * Admins can convert any booking to trip.
     */
    public function createFromBooking(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user->role->isSuperAdmin() || $user->role->value === 'ADMIN';

        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
        ]);

        $booking = Booking::query()->with(['ride', 'user'])->findOrFail((int) $validated['booking_id']);
        
        // Passengers can only convert their own bookings, admins can convert any
        if (!$isAdmin && (int) $booking->user_id !== (int) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only convert your own bookings to trips',
            ], 403);
        }

        if (! in_array(strtoupper((string) $booking->status), ['PENDING', 'CONFIRMED'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Booking must be PENDING or CONFIRMED to convert to trip',
            ], 422);
        }

        // Ensure booking has required location data
        if (!$booking->pickup_address || !$booking->dropoff_address) {
            return response()->json([
                'success' => false,
                'message' => 'Booking must have pickup and dropoff locations',
            ], 422);
        }

        try {
            // Enforce transport rules first
            RidePolicy::assertTransportRules($booking->ride);
            RidePolicy::assertBookingAllowed($booking->ride);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ], 422);
        }

        $existing = Trip::query()->where('booking_id', $booking->id)->first();
        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Trip already exists for booking',
                'data' => [
                    'id' => $existing->id,
                    'booking_id' => $existing->booking_id,
                    'ride_id' => $existing->ride_id,
                    'status' => $existing->status,
                ],
            ]);
        }

        $passengerMobileUserId = $booking->user?->mobile_user_id
            ? (int) $booking->user->mobile_user_id
            : (int) $booking->user_id;

        $trip = new Trip([
            'booking_id' => $booking->id,
            'ride_id' => $booking->ride_id,
            'passenger_id' => $passengerMobileUserId,
            'driver_id' => $booking->ride?->driver_id,
            'pickup_location' => $booking->pickup_address ?: $booking->ride?->origin_address,
            'pickup_lat' => $booking->pickup_lat ?: $booking->ride?->origin_lat,
            'pickup_lng' => $booking->pickup_lng ?: $booking->ride?->origin_lng,
            'dropoff_location' => $booking->dropoff_address ?: $booking->ride?->destination_address,
            'dropoff_lat' => $booking->dropoff_lat ?: $booking->ride?->destination_lat,
            'dropoff_lng' => $booking->dropoff_lng ?: $booking->ride?->destination_lng,
            'fare' => $booking->total_price,
            'status' => strtoupper((string) (strtoupper((string) $booking->status) === 'COMPLETED' ? 'COMPLETED' : 'PENDING')),
            'requested_at' => $booking->created_at,
        ]);
        $trip->validateForExecution();
        $trip->save();

        $booking->update([
            'status' => strtoupper((string) $booking->status) === 'COMPLETED' ? 'COMPLETED' : 'CONFIRMED',
            'confirmed_at' => $booking->confirmed_at ?: now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Trip created from booking successfully',
            'data' => [
                'id' => $trip->id,
                'booking_id' => $trip->booking_id,
                'ride_id' => $trip->ride_id,
                'status' => $trip->status,
            ],
        ], 201);
    }

    /**
     * Accept a trip request (Driver).
     */
    public function accept(Request $request, int $id): JsonResponse
    {
        DomainGuard::assertUsingPolicy(__METHOD__);

        $trip = Trip::findOrFail($id);
        $user = $request->user();
        
        // Check if user is a driver
        if (!$user->isDriver()) {
            return response()->json([
                'success' => false,
                'message' => 'Only drivers can accept trip requests',
            ], 403);
        }
        
        try {
            TripStateMachine::assertTransitionForTrip($trip, TripStateMachine::ACCEPTED);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ], 422);
        }
        
        // Get driver profile
        $driver = $user->driver;
        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found',
            ], 404);
        }

        try {
            DriverPolicy::assertCanAcceptTrip($driver, $trip);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ], 422);
        }
        
        $trip->update([
            'driver_id' => $driver->id,
            'status' => 'ACCEPTED',
            'accepted_at' => now(),
        ]);

        event(new TripMatched((int) $trip->id, (int) $driver->id));

        $this->mobileNotificationService->sendRideAcceptedToPassenger($trip->fresh(), $driver);

        app(AITrainingDataLogger::class)->logTripEvent($trip->fresh(), 'driver_assigned', [
            'driver_id' => $driver->id,
        ]);
        app(AITrainingDataLogger::class)->syncRideSnapshot($trip->fresh());
        
        return response()->json([
            'success' => true,
            'message' => 'Trip accepted successfully',
            'data' => [
                'id' => $trip->id,
                'status' => $trip->status,
                'trip_state' => $trip->status,
                'driver_location' => $this->tripLocationService->getCurrentLocation($trip),
                'eta' => 12,
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
        
        try {
            TripStateMachine::assertTransitionForTrip($trip, TripStateMachine::STARTED);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ], 422);
        }
        
        $trip->update([
            'status' => 'STARTED',
            'started_at' => now(),
        ]);

        event(new TripStarted((int) $trip->id));

        $this->mobileNotificationService->sendTripStartedToPassenger($trip->fresh());

        app(AITrainingDataLogger::class)->logTripEvent($trip->fresh(), 'ride_started');
        app(AITrainingDataLogger::class)->syncRideSnapshot($trip->fresh());
        
        return response()->json([
            'success' => true,
            'message' => 'Trip started successfully',
            'data' => [
                'id' => $trip->id,
                'status' => $trip->status,
                'trip_state' => $trip->status,
                'driver_location' => $this->tripLocationService->getCurrentLocation($trip),
                'eta' => 12,
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
        
        try {
            TripStateMachine::assertTransitionForTrip($trip, TripStateMachine::COMPLETED);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ], 422);
        }
        
        $trip->update([
            'status' => 'COMPLETED',
            'completed_at' => now(),
        ]);

        event(new TripCompleted((int) $trip->id));

        $this->mobileNotificationService->sendTripCompletedToPassenger($trip->fresh());

        app(AITrainingDataLogger::class)->logTripEvent($trip->fresh(), 'ride_completed');
        app(AITrainingDataLogger::class)->syncRideSnapshot($trip->fresh());
        
        return response()->json([
            'success' => true,
            'message' => 'Trip completed successfully',
            'data' => [
                'id' => $trip->id,
                'status' => $trip->status,
                'trip_state' => $trip->status,
                'driver_location' => $this->tripLocationService->getCurrentLocation($trip),
                'eta' => 12,
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

        $passengerMobileUserId = null;
        if ($user->isPassenger()) {
            $passengerMobileUserId = $user->mobile_user_id
                ? (int) $user->mobile_user_id
                : (int) (MobileUser::query()->where('email', $user->email)->value('id') ?? 0);
        }

        // Check if user is passenger, driver, or admin
        $isPassenger = $passengerMobileUserId > 0
            && (int) $trip->passenger_id === (int) $passengerMobileUserId;
        $isDriver = $trip->driver?->user_id === $user->id;
        $isAdmin = $user->role->isSuperAdmin() || $user->role->isManager();
        
        if (!$isPassenger && !$isDriver && !$isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to cancel this trip',
            ], 403);
        }
        
        try {
            TripStateMachine::assertTransitionForTrip($trip, TripStateMachine::CANCELLED);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ], 422);
        }
        
        $trip->update([
            'status' => 'CANCELLED',
        ]);

        $trip = $trip->fresh();
        $reason = (string) $request->input('reason', 'cancelled_by_user');
        $this->mobileNotificationService->sendTripCancelledToPassenger($trip, $reason);
        $this->mobileNotificationService->sendTripCancelledToDriver($trip, $reason);

        app(AITrainingDataLogger::class)->logTripCancellation(
            $trip,
            $user->id,
            $reason
        );
        
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
        $passengerMobileUserId = $user->mobile_user_id ? (int) $user->mobile_user_id : null;
        $driverId = $user->driver?->id ? (int) $user->driver->id : null;
        
        $query = Trip::query();
        
        // Filter by role (passenger or driver)
        $type = $request->get('type', 'all'); // 'passenger', 'driver', or 'all'
        
        if ($type === 'passenger') {
            if (! $passengerMobileUserId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Passenger mobile profile is not linked',
                ], 422);
            }

            $query->where('passenger_id', $passengerMobileUserId);
        } elseif ($type === 'driver') {
            if (! $driverId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Driver profile not found',
                ], 422);
            }

            $query->where('driver_id', $driverId);
        } else {
            $query->where(function ($q) use ($passengerMobileUserId, $driverId) {
                if ($passengerMobileUserId) {
                    $q->where('passenger_id', $passengerMobileUserId);
                }

                if ($driverId) {
                    $q->orWhere('driver_id', $driverId);
                }
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
                'trip_state' => $trip->status,
                'driver_location' => $this->tripLocationService->getCurrentLocation($trip),
                'eta' => 12,
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

    private function resolvePassengerMobileUserId($user): int
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
