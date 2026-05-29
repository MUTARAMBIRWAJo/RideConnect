<?php

namespace App\Http\Controllers\Api;

use App\Domain\Core\DomainGuard;
use App\Domain\Driver\DriverPolicy;
use App\Domain\Ride\RidePolicy;
use App\Domain\Trip\TripStateMachine;
use App\Events\Domain\TripCompleted;
use App\Events\Domain\TripMatched;
use App\Events\Domain\TripStarted;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Jobs\FindAndNotifyDriverJob;
use App\Models\Booking;
use App\Models\MatchingSession;
use App\Models\MobileUser;
use App\Models\Ride;
use App\Models\RideEvent;
use App\Models\Trip;
use App\Models\TripStatusEvent;
use App\Models\User;
use App\Services\AITrainingDataLogger;
use App\Services\DriverAssignmentService;
use App\Services\FareCalculatorService;
use App\Services\Location\TripLocationService;
use App\Services\MobileNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\TripCompletionService;
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
        private readonly TripCompletionService $tripCompletionService,
        private readonly FareCalculatorService $fareCalculatorService,
    ) {}

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
                $passengerIds = array_filter([(int) $user->mobile_user_id, (int) $user->id]);
                if (! empty($passengerIds)) {
                    $q->whereIn('passenger_id', $passengerIds);
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
                'data' => $trips->map(fn ($trip) => [
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
                'pickup_place_name' => $trip->pickup_place_name,
                'pickup_lat' => $trip->pickup_lat,
                'pickup_lng' => $trip->pickup_lng,
                'dropoff_location' => $trip->dropoff_location,
                'dropoff_place_name' => $trip->dropoff_place_name,
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
            $passengerIds = array_filter([(int) $user->mobile_user_id, (int) $user->id]);
            $isPassenger = in_array((int) $trip->passenger_id, $passengerIds, true);
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
                'pickup_place_name' => $trip->pickup_place_name,
                'pickup_lat' => $trip->pickup_lat,
                'pickup_lng' => $trip->pickup_lng,
                'dropoff_location' => $trip->dropoff_location,
                'dropoff_place_name' => $trip->dropoff_place_name,
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
        $validator = Validator::make($request->all(), [
            'passenger_id' => 'required|integer|exists:mobile_users,id',
            'pickup_location' => 'required|string',
            'dropoff_location' => 'required|string',
            'pickup_lat' => 'required|numeric|between:-90,90',
            'pickup_lng' => 'required|numeric|between:-180,180',
            'dropoff_lat' => 'required|numeric|between:-90,90',
            'dropoff_lng' => 'required|numeric|between:-180,180',
            'transport_type' => 'required|in:moto,car,bus',
            'payment_method' => 'required|string',
            'pickup_place_name' => 'nullable|string|max:255',
            'dropoff_place_name' => 'nullable|string|max:255',
            'pickup_zone' => 'nullable|string|max:64',
            'dropoff_zone' => 'nullable|string|max:64',
            'idempotency_key' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'code' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        if (! empty($validated['idempotency_key'])) {
            $existing = Trip::query()
                ->with(['matchingSession', 'passenger:id,first_name,last_name,phone'])
                ->where('passenger_id', $validated['passenger_id'])
                ->where('idempotency_key', $validated['idempotency_key'])
                ->first();

            if ($existing) {
                return response()->json([
                    'status' => 'success',
                    'code' => 200,
                    'message' => 'Trip already exists',
                    'data' => $existing,
                ]);
            }
        }

        $trip = DB::transaction(function () use ($validated): Trip {
            $now = now();
            $fare = $this->fareCalculatorService->estimate(
                (float) $validated['pickup_lat'],
                (float) $validated['pickup_lng'],
                (float) $validated['dropoff_lat'],
                (float) $validated['dropoff_lng'],
                $validated['transport_type'],
            );

            $matchingSession = MatchingSession::query()->create([
                'matching_session_id' => (string) Str::uuid(),
                'passenger_id' => $validated['passenger_id'],
                'transport_type' => $validated['transport_type'],
                'pickup_lat' => $validated['pickup_lat'],
                'pickup_lng' => $validated['pickup_lng'],
                'dropoff_lat' => $validated['dropoff_lat'],
                'dropoff_lng' => $validated['dropoff_lng'],
                'status' => 'searching',
                'expires_at' => $now->copy()->addMinutes(5),
            ]);

            $trip = Trip::query()->create([
                'passenger_id' => $validated['passenger_id'],
                'pickup_location' => $validated['pickup_location'],
                'dropoff_location' => $validated['dropoff_location'],
                'pickup_lat' => $validated['pickup_lat'],
                'pickup_lng' => $validated['pickup_lng'],
                'dropoff_lat' => $validated['dropoff_lat'],
                'dropoff_lng' => $validated['dropoff_lng'],
                'pickup_place_name' => $validated['pickup_place_name'] ?? null,
                'dropoff_place_name' => $validated['dropoff_place_name'] ?? null,
                'pickup_zone' => $validated['pickup_zone'] ?? null,
                'dropoff_zone' => $validated['dropoff_zone'] ?? null,
                'transport_type' => $validated['transport_type'],
                'fare' => $fare,
                'status' => 'requested',
                'payment_status' => 'unpaid',
                'assignment_status' => 'unassigned',
                'rejected_drivers_count' => 0,
                'matching_session_id' => $matchingSession->matching_session_id,
                'idempotency_key' => $validated['idempotency_key'] ?? null,
                'requested_at' => $now,
            ]);

            TripStatusEvent::query()->create([
                'trip_id' => $trip->id,
                'actor_type' => 'passenger',
                'actor_id' => $validated['passenger_id'],
                'old_status' => null,
                'new_status' => 'requested',
                'created_at' => $now,
            ]);

            RideEvent::query()->create([
                'trip_id' => $trip->id,
                'passenger_id' => $validated['passenger_id'],
                'event_type' => 'trip_requested',
                'event_time' => $now,
            ]);

            DB::table('demand_logs')->insert([
                'trip_id' => $trip->id,
                'pickup_lat' => $validated['pickup_lat'],
                'pickup_lng' => $validated['pickup_lng'],
                'request_time' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::afterCommit(fn () => FindAndNotifyDriverJob::dispatch((int) $trip->id));

            return $trip;
        });

        return response()->json([
            'status' => 'success',
            'code' => 201,
            'message' => 'Trip created',
            'data' => $trip->fresh(['matchingSession', 'passenger:id,first_name,last_name,phone']),
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
        if (! $isAdmin && (int) $booking->user_id !== (int) $user->id) {
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
        if (! $booking->pickup_address || ! $booking->dropoff_address) {
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

        $passengerMobileUserId = $this->resolveOrCreatePassengerMobileUserId($booking->user);

        $trip = new Trip([
            'booking_id' => $booking->id,
            'ride_id' => $booking->ride_id,
            'passenger_id' => $passengerMobileUserId,
            'driver_id' => null,
            'pickup_location' => $booking->pickup_address ?: $booking->ride?->origin_address,
            'pickup_place_name' => $booking->pickup_address ?: $booking->ride?->origin_address,
            'pickup_lat' => $booking->pickup_lat ?: $booking->ride?->origin_lat,
            'pickup_lng' => $booking->pickup_lng ?: $booking->ride?->origin_lng,
            'dropoff_location' => $booking->dropoff_address ?: $booking->ride?->destination_address,
            'dropoff_place_name' => $booking->dropoff_address ?: $booking->ride?->destination_address,
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
        if (! $user->isDriver()) {
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
        if (! $driver) {
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
        if ($trip->driver?->user_id !== $user->id && ! $user->role->isSuperAdmin()) {
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
        if ($trip->driver?->user_id !== $user->id && ! $user->role->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only the assigned driver can complete this trip',
            ], 403);
        }

        try {
            $trip = $this->tripCompletionService->complete(
                (int) $trip->id,
                (int) $user->id,
                $user->role->isSuperAdmin(),
                $user->role->isSuperAdmin() ? 'admin override from API' : null
            );
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ], 422);
        }

        $this->mobileNotificationService->sendTripCompletedToPassenger($trip);

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

        if (! $isPassenger && ! $isDriver && ! $isAdmin) {
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
        $passengerFallbackIds = array_filter([(int) $user->mobile_user_id, (int) $user->id]);

        $query = Trip::query();

        // Filter by role (passenger or driver)
        $type = $request->get('type', 'all'); // 'passenger', 'driver', or 'all'

        if ($type === 'passenger') {
            if (empty($passengerFallbackIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Passenger mobile profile is not linked',
                ], 422);
            }

            $query->whereIn('passenger_id', $passengerFallbackIds);
        } elseif ($type === 'driver') {
            if (! $driverId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Driver profile not found',
                ], 422);
            }

            $query->where('driver_id', $driverId);
        } else {
            $query->where(function ($q) use ($passengerFallbackIds, $driverId) {
                if (! empty($passengerFallbackIds)) {
                    $q->whereIn('passenger_id', $passengerFallbackIds);
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
            'data' => $trips->map(fn ($trip) => [
                'id' => $trip->id,
                'type' => in_array((int) $trip->passenger_id, $passengerFallbackIds, true) ? 'passenger' : 'driver',
                'passenger' => [
                    'id' => $trip->passenger?->id,
                    'name' => $trip->passenger?->name,
                ],
                'driver' => [
                    'id' => $trip->driver?->id,
                    'name' => $trip->driver?->name,
                ],
                'pickup_location' => $trip->pickup_location,
                'pickup_place_name' => $trip->pickup_place_name,
                'pickup_lat' => $trip->pickup_lat,
                'pickup_lng' => $trip->pickup_lng,
                'dropoff_location' => $trip->dropoff_location,
                'dropoff_place_name' => $trip->dropoff_place_name,
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
            'data' => $trips->map(fn ($trip) => [
                'id' => $trip->id,
                'passenger' => [
                    'id' => $trip->passenger?->id,
                    'name' => $trip->passenger?->name,
                    'phone' => $trip->passenger?->phone,
                ],
                'pickup_location' => $trip->pickup_location,
                'pickup_place_name' => $trip->pickup_place_name,
                'pickup_lat' => $trip->pickup_lat,
                'pickup_lng' => $trip->pickup_lng,
                'dropoff_location' => $trip->dropoff_location,
                'dropoff_place_name' => $trip->dropoff_place_name,
                'dropoff_lat' => $trip->dropoff_lat,
                'dropoff_lng' => $trip->dropoff_lng,
                'fare' => $trip->fare,
                'requested_at' => $trip->requested_at?->toIso8601String(),
            ]),
        ]);
    }

    private function resolvePassengerMobileUserId($user): int
    {
        // Prefer explicit mobile_user_id when available.
        if ($user->mobile_user_id) {
            return (int) $user->mobile_user_id;
        }

        // Try to find a MobileUser with same email (legacy linkage)
        $mobileUserId = MobileUser::query()
            ->where('email', $user->email)
            ->value('id');

        if ($mobileUserId) {
            return (int) $mobileUserId;
        }

        // Fallback to the application user id to remain compatible with existing
        // booking/trip records that may have been stored with user ids instead
        // of mobile_user ids. This avoids throwing exceptions and keeps the
        // passenger able to view their trips.
        return (int) $user->id;
    }

    private function resolveOrCreatePassengerMobileUserId(?User $user): int
    {
        if (! $user) {
            throw new \InvalidArgumentException('Passenger user is required');
        }

        if ($user->mobile_user_id) {
            return (int) $user->mobile_user_id;
        }

        $mobileUser = MobileUser::query()->firstOrCreate(
            ['email' => $user->email],
            [
                'first_name' => trim((string) explode(' ', (string) $user->name)[0]) ?: 'Passenger',
                'last_name' => trim((string) (explode(' ', (string) $user->name, 2)[1] ?? 'User')) ?: 'User',
                'phone' => $user->phone ?? '+250700000000',
                'password' => $user->password ?? bcrypt('password'),
                'role' => 'PASSENGER',
                'is_verified' => true,
            ]
        );

        if (! $user->mobile_user_id) {
            $user->forceFill(['mobile_user_id' => $mobileUser->id])->save();
        }

        return (int) $mobileUser->id;
    }
}
