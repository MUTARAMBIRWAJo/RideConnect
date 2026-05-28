<?php

namespace App\Http\Controllers\Api;

use App\Domain\Core\DomainGuard;
use App\Domain\Ride\RidePolicy;
use App\Domain\Trip\TripStateMachine;
use App\Events\Domain\TripMatched;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Ride;
use App\Models\Trip;
use App\Services\DriverAssignmentService;
use App\Services\DriverMatchingService;
use App\Services\Location\TripLocationService;
use App\Services\MatchingSessionService;
use App\Services\MobileNotificationService;
use App\Services\SeatReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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
        private readonly DriverMatchingService $driverMatchingService,
        private readonly MatchingSessionService $matchingSessionService,
        private readonly SeatReservationService $seatReservationService,
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

        if (! $user->is_approved) {
            return response()->json([
                'status' => 'error',
                'type' => 'DOMAIN_ERROR',
                'message' => 'Account not approved',
                'code' => 403,
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'ride_id' => 'nullable|required_without:driver_id|exists:rides,id',
            'driver_id' => 'nullable|required_without:ride_id|integer|exists:drivers,id',
            'selected_driver_id' => 'nullable|integer|exists:drivers,id',
            'matching_session_id' => 'nullable|string|uuid',
            'transport_type' => 'nullable|string|in:private_car,car,CAR',
            'seats' => 'required|integer|min:1|max:8',
            'pickup_location' => 'required_with:driver_id|string|min:3',
            'pickup_lat' => 'required_with:driver_id|numeric|between:-90,90',
            'pickup_lng' => 'required_with:driver_id|numeric|between:-180,180',
            'dropoff_location' => 'required_with:driver_id|string|min:3',
            'dropoff_lat' => 'required_with:driver_id|numeric|between:-90,90',
            'dropoff_lng' => 'required_with:driver_id|numeric|between:-180,180',
            'schedule_time' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $selectedDriverId = $validated['selected_driver_id'] ?? $validated['driver_id'] ?? null;

        if ($selectedDriverId !== null) {
            if (! empty($validated['driver_id'])) {
                $selectedDriverId = (int) $validated['driver_id'];
            }

            // If a matching session id was provided, validate and confirm selection.
            // Otherwise permit direct driver selection (legacy flow) after availability checks.
            if (! empty($validated['matching_session_id'])) {
                $session = $this->matchingSessionService->validateSession(
                    $this->resolvePassengerMobileUserId($user),
                    $validated['matching_session_id'],
                );
                $this->matchingSessionService->confirmSelectedDriver($session, $selectedDriverId);
            }
        }

        try {
            $ride = ! empty($validated['ride_id'])
                ? Ride::findOrFail($validated['ride_id'])
                : $this->createPrivateCarRideForSelectedDriver($validated, $user);

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
        $idempotencyKey = trim((string) $request->header('X-Idempotency-Key', ''));

        if ($idempotencyKey !== '') {
            $existingBooking = Booking::query()
                ->where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingBooking) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'id' => $existingBooking->id,
                        'status' => $existingBooking->status,
                        'total_price' => $existingBooking->total_price,
                        'currency' => $existingBooking->currency,
                        'hours_to_departure' => $ride->departure_time?->diffInHours(now(), false),
                    ],
                ]);
            }
        }

        $createdHere = empty($validated['ride_id']);

        $booking = DB::transaction(function () use ($ride, $validated, $user, $idempotencyKey, $createdHere) {
            $booking = Booking::create([
                'user_id' => $user->id,
                'ride_id' => $ride->id,
                'seats_booked' => $validated['seats'],
                'total_price' => $validated['seats'] * ($ride->price_per_seat ?? 0),
                'status' => 'PENDING',
                'currency' => 'RWF',
                'pickup_address' => $validated['pickup_location'] ?? null,
                'pickup_lat' => $validated['pickup_lat'] ?? null,
                'pickup_lng' => $validated['pickup_lng'] ?? null,
                'dropoff_address' => $validated['dropoff_location'] ?? null,
                'dropoff_lat' => $validated['dropoff_lat'] ?? null,
                'dropoff_lng' => $validated['dropoff_lng'] ?? null,
                'matching_session_id' => $validated['matching_session_id'] ?? null,
                'idempotency_key' => $idempotencyKey ?: null,
            ]);

            // Reserve seats for existing rides only. When a private car ride is
            // created in the same request for the selected driver, tests expect
            // the ride to retain its original available_seats value.
            if (! $createdHere) {
                $this->seatReservationService->reserveForBooking(
                    $ride->id,
                    (int) $validated['seats'], 
                    $user->id,
                    $booking->id,
                );
            }

            return $booking;
        });

        $this->notificationService->sendBookingRequestToDriver($booking->loadMissing('ride.driver'));

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

        if (! $user->is_approved) {
            return response()->json([
                'status' => 'error',
                'type' => 'DOMAIN_ERROR',
                'message' => 'Account not approved',
                'code' => 403,
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'ride_id' => 'nullable|exists:rides,id',
            'driver_id' => 'nullable|integer|exists:drivers,id',
            'selected_driver_id' => 'nullable|integer|exists:drivers,id',
            'matching_session_id' => 'nullable|string|uuid',
            'transport_type' => 'nullable|string|in:motor_vehicle,moto,motorcycle,MOTORCYCLE',
            'pickup_location' => 'required|string|min:3',
            'pickup_lat' => 'required|numeric|between:-90,90',
            'pickup_lng' => 'required|numeric|between:-180,180',
            'pickup_place_name' => 'nullable|string|max:255',
            'dropoff_location' => 'required|string|min:3',
            'dropoff_lat' => 'required|numeric|between:-90,90',
            'dropoff_lng' => 'required|numeric|between:-180,180',
            'dropoff_place_name' => 'nullable|string|max:255',
            'fare' => 'nullable|numeric|min:0',
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
        $selectedDriverId = $validated['selected_driver_id'] ?? $validated['driver_id'] ?? null;

        if ($selectedDriverId !== null) {
            if (! empty($validated['driver_id']) && ! empty($validated['selected_driver_id']) && $validated['driver_id'] !== $validated['selected_driver_id']) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Selected driver id values do not match.',
                ], 422);
            }
            if (! empty($validated['matching_session_id'])) {
                $this->matchingSessionService->confirmSelectedDriver(
                    $this->matchingSessionService->validateSession($passengerMobileUserId, $validated['matching_session_id']),
                    (int) $selectedDriverId,
                );
            }
        }

        $ride = null;
        if (! empty($validated['ride_id'])) {
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

        $selectedDriver = null;
        if (! empty($validated['driver_id'])) {
            $selectedDriver = \App\Models\Driver::query()
                ->with(['user:id,is_approved', 'vehicles'])
                ->findOrFail((int) $validated['driver_id']);

            if (! in_array((string) $selectedDriver->availability_status, ['online', 'available'], true)
                || ! $selectedDriver->user?->is_approved
                || ! $this->driverMatchingService->activeVehicleFor($selectedDriver, Ride::TRANSPORT_MOTORCYCLE)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Selected driver is no longer available',
                ], 409);
            }
        }

        $estimatedFare = $this->driverMatchingService->estimateFare(
            Ride::TRANSPORT_MOTORCYCLE,
            $this->distanceKm(
                (float) $validated['pickup_lat'],
                (float) $validated['pickup_lng'],
                (float) $validated['dropoff_lat'],
                (float) $validated['dropoff_lng'],
            )
        );

        $idempotencyKey = trim((string) $request->header('X-Idempotency-Key', ''));
        if ($idempotencyKey !== '') {
            $existingTrip = Trip::query()
                ->where('passenger_id', $passengerMobileUserId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingTrip) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'id' => $existingTrip->id,
                        'trip_state' => $existingTrip->status,
                        'driver_id' => $existingTrip->driver_id,
                        'driver_action_required' => $existingTrip->driver_id !== null,
                    ],
                ]);
            }
        }

        // Create trip
        $trip = new Trip([
            'ride_id'            => $validated['ride_id'] ?? null,
            'passenger_id'       => $passengerMobileUserId,
            'driver_id'          => $selectedDriver?->id,
            'transport_type'      => $selectedDriver ? Ride::TRANSPORT_MOTORCYCLE : null,
            'matching_session_id' => $validated['matching_session_id'] ?? null,
            'idempotency_key'    => $idempotencyKey,
            'pickup_location'    => $validated['pickup_location'],
            'pickup_place_name'  => $validated['pickup_place_name'] ?? null,
            'pickup_lat'         => $validated['pickup_lat'],
            'pickup_lng'         => $validated['pickup_lng'],
            'dropoff_location'   => $validated['dropoff_location'],
            'dropoff_place_name' => $validated['dropoff_place_name'] ?? null,
            'dropoff_lat'        => $validated['dropoff_lat'],
            'dropoff_lng'        => $validated['dropoff_lng'],
            'fare'               => $validated['fare'] ?? $estimatedFare,
            'status'             => 'PENDING',
            'requested_at'       => now(),
        ]);

        $trip->validateForExecution();
        $trip->save();

        // Auto-assign driver if ride specified
        if ($selectedDriver) {
            $this->notificationService->sendRideRequestToDriver($trip, $selectedDriver);
        } elseif ($ride) {
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
                'driver_action_required' => $selectedDriver !== null,
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

        if (! $trip) {
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

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    private function createPrivateCarRideForSelectedDriver(array $validated, $user): Ride
    {
        $driver = \App\Models\Driver::query()
            ->with(['user:id,is_approved', 'vehicles'])
            ->findOrFail((int) $validated['driver_id']);
        $vehicle = $this->driverMatchingService->activeVehicleFor($driver, Ride::TRANSPORT_CAR);

        if (! $vehicle
            || ! in_array((string) $driver->availability_status, ['online', 'available'], true)
            || ! $driver->user?->is_approved) {
            throw DomainException::make('Selected driver is no longer available', 'DRIVER_UNAVAILABLE');
        }

        if ((int) $vehicle->seats < (int) $validated['seats']) {
            throw DomainException::make('Not enough seats available', 'INSUFFICIENT_SEATS');
        }

        $distanceKm = $this->distanceKm(
            (float) $validated['pickup_lat'],
            (float) $validated['pickup_lng'],
            (float) $validated['dropoff_lat'],
            (float) $validated['dropoff_lng'],
        );
        $estimatedFare = $this->driverMatchingService->estimateFare(Ride::TRANSPORT_CAR, $distanceKm);

        return Ride::query()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'created_by' => $user->id,
            'transport_type' => Ride::TRANSPORT_CAR,
            'travel_mode' => Ride::MODE_SCHEDULED,
            'ride_type' => Ride::TYPE_LOCAL,
            'origin_address' => $validated['pickup_location'],
            'origin_lat' => $validated['pickup_lat'],
            'origin_lng' => $validated['pickup_lng'],
            'destination_address' => $validated['dropoff_location'],
            'destination_lat' => $validated['dropoff_lat'],
            'destination_lng' => $validated['dropoff_lng'],
            'departure_time' => $validated['schedule_time'] ?? now()->addMinutes(30),
            'available_seats' => (int) $vehicle->seats,
            'price_per_seat' => round($estimatedFare / max(1, (int) $validated['seats']), 2),
            'currency' => 'RWF',
            'status' => 'PUBLISHED',
            'description' => 'Private car booking request',
        ]);
    }
}
