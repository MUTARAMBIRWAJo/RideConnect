<?php

namespace App\Http\Controllers\Api;

use App\Domain\Ride\RidePolicy;
use App\Events\Domain\RideCreated;
use App\Http\Controllers\Controller;
use App\Models\Corridor;
use App\Models\Ride;
use App\Models\TransportRoute;
use App\Services\AiPredictionService;
use App\Services\MobileNotificationService;
use App\Services\RideCategoryTransitionService;
use App\Services\RuraTariffService;
use App\Services\RuraZoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RideController extends Controller
{
    private const DEFAULT_TICKET_THRESHOLD_HOURS = 6;

    public function __construct(
        private readonly RideCategoryTransitionService $rideCategoryTransitionService,
        private readonly MobileNotificationService $mobileNotificationService,
        private readonly AiPredictionService $aiPredictionService,
        private readonly RuraZoneService $ruraZoneService,
        private readonly RuraTariffService $ruraTariffService,
    ) {}

    /**
     * Display a listing of rides.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Ride::with(['driver.user', 'vehicle', 'corridor', 'route.corridor']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by transport_type
        if ($request->has('transport_type')) {
            $query->where('transport_type', $request->transport_type);
        }

        // Filter by travel_mode
        if ($request->has('travel_mode')) {
            $query->where('travel_mode', $request->travel_mode);
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
                ->where('status', 'PUBLISHED')
                ->where('departure_time', '>', now());
        }

        $rides = $query
            ->orderBy('origin_address', 'asc')
            ->orderBy('destination_address', 'asc')
            ->orderBy('departure_time', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rides->map(fn (Ride $ride) => $this->ridePayload($ride)),
        ]);
    }

    /**
     * Display the specified ride.
     */
    public function show(int $id): JsonResponse
    {
        $ride = Ride::with(['driver.user', 'vehicle', 'bookings', 'reviews', 'corridor', 'route.corridor'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => array_merge(
                $this->ridePayload($ride),
                [
                    'bookings_count' => $ride->bookings->count(),
                    'reviews_count' => $ride->reviews->count(),
                    'average_rating' => $ride->reviews->avg('rating'),
                ]
            ),
        ]);
    }

    /**
     * Create a new ride (Admin / Super Admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || Gate::forUser($user)->denies('create', Ride::class)) {
            return response()->json([
                'success' => false,
                'message' => 'Only Admin or Super Admin can create rides',
            ], 403);
        }

        $validated = $request->validate([
            'driver_id' => 'required|exists:drivers,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'zone_id' => 'required|exists:zones,id',
            'corridor_id' => 'required|exists:corridors,id',
            'origin_lat' => 'required|numeric|between:-90,90',
            'origin_lng' => 'required|numeric|between:-180,180',
            'destination_lat' => 'required|numeric|between:-90,90',
            'destination_lng' => 'required|numeric|between:-180,180',
            'distance_km' => 'nullable|numeric|min:0',
            'departure_time' => 'required|date|after:now',
            'arrival_time_estimated' => 'nullable|date|after:departure_time',
            'available_seats' => 'required|integer|min:1|max:8',
            'currency' => 'sometimes|string|size:3',
            'description' => 'nullable|string',
            'ride_type' => ['sometimes', 'string', Rule::in([Ride::TYPE_INTERCITY, Ride::TYPE_LOCAL])],
            'luggage_allowed' => 'sometimes|boolean',
            'pets_allowed' => 'sometimes|boolean',
            'smoking_allowed' => 'sometimes|boolean',
            'status' => 'nullable|in:DRAFT,PUBLISHED',
        ]);

        $corridor = Corridor::with(['startZone', 'endZone'])->findOrFail((int) $validated['corridor_id']);
        $distanceKm = (float) ($validated['distance_km'] ?? 1);
        $pricePerSeat = round(((float) $corridor->base_fare) + ($distanceKm * (float) $corridor->price_per_km), 2);

        $ride = Ride::create([
            'driver_id' => (int) $validated['driver_id'],
            'vehicle_id' => (int) $validated['vehicle_id'],
            'zone_id' => (int) $validated['zone_id'],
            'corridor_id' => (int) $validated['corridor_id'],
            'created_by' => (int) $user->id,
            'origin_address' => $corridor->startZone?->name ?? 'Start Zone',
            'origin_lat' => $validated['origin_lat'],
            'origin_lng' => $validated['origin_lng'],
            'destination_address' => $corridor->endZone?->name ?? 'End Zone',
            'destination_lat' => $validated['destination_lat'],
            'destination_lng' => $validated['destination_lng'],
            'departure_time' => $validated['departure_time'],
            'arrival_time_estimated' => $validated['arrival_time_estimated'] ?? null,
            'available_seats' => (int) $validated['available_seats'],
            'price_per_seat' => $pricePerSeat,
            'currency' => $validated['currency'] ?? 'RWF',
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'PUBLISHED',
            'ride_type' => $validated['ride_type'] ?? 'local',
            'luggage_allowed' => (bool) ($validated['luggage_allowed'] ?? true),
            'pets_allowed' => (bool) ($validated['pets_allowed'] ?? false),
            'smoking_allowed' => (bool) ($validated['smoking_allowed'] ?? false),
        ]);

        event(new RideCreated((int) $ride->id, (int) $ride->driver_id));

        return response()->json([
            'success' => true,
            'message' => 'Ride created successfully',
            'data' => [
                'id' => $ride->id,
                'status' => $ride->status,
                'zone_id' => $ride->zone_id,
                'corridor_id' => $ride->corridor_id,
                'price_per_seat' => $ride->price_per_seat,
            ],
        ], 201);
    }

    /**
     * Explicit admin endpoint for corridor-driven ride creation.
     */
    public function createRideWithCorridor(Request $request): JsonResponse
    {
        return $this->store($request);
    }

    /**
     * Update the specified ride.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $ride = Ride::findOrFail($id);
        $user = $request->user();
        $originalDepartureTime = $ride->departure_time;

        if (! $user || Gate::forUser($user)->denies('update', $ride)) {
            return response()->json([
                'success' => false,
                'message' => 'Only Admin or Super Admin can update rides',
            ], 403);
        }

        // Cannot update if ride has already started
        if (in_array(strtoupper((string) $ride->status), ['IN_PROGRESS', 'COMPLETED', 'CANCELLED'], true)) {
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
            'status' => 'sometimes|in:DRAFT,PUBLISHED,IN_PROGRESS,COMPLETED,CANCELLED',
        ]);

        // If cancelling, add cancellation reason
        if (isset($validated['status']) && strtoupper((string) $validated['status']) === 'CANCELLED') {
            $validated['cancelled_at'] = now();
            $validated['cancellation_reason'] = $request->cancellation_reason;
        }

        $ride->update($validated);

        if (array_key_exists('departure_time', $validated)
            || ($originalDepartureTime && $this->rideCategoryTransitionService->isTripCategory($ride))) {
            $this->rideCategoryTransitionService->synchronizeTravelCategories($ride);
        }

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

        if (! $user || Gate::forUser($user)->denies('delete', $ride)) {
            return response()->json([
                'success' => false,
                'message' => 'Only Admin or Super Admin can delete rides',
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

        if (! $user->isDriver()) {
            return response()->json([
                'success' => false,
                'message' => 'Only drivers can access this resource',
            ], 403);
        }

        $driver = $user->driver;
        if (! $driver) {
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
            'data' => $rides->map(fn ($ride) => [
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
        if (! $user->isPassenger()) {
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
        if (! in_array(strtoupper((string) $ride->status), ['PUBLISHED'], true)) {
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

        // Detect RURA zones for origin/destination
        $originZone = $this->ruraZoneService->coordsToZone($ride->origin_lat, $ride->origin_lng);
        $destZone = $this->ruraZoneService->coordsToZone($ride->destination_lat, $ride->destination_lng);

        // Try to lookup legal RURA fare
        $tariff = $this->ruraTariffService->lookupTariff(
            null,
            $originZone.' Bus Park',
            $destZone.' Bus Park',
            null
        );
        if ($tariff && isset($tariff['fare_rwf'])) {
            $totalPrice = $tariff['fare_rwf'] * $validated['seats'];
            $corridor = $tariff['corridor'] ?? null;
        } else {
            // Fallback: Predict price using AI service
            $aiPayload = [
                'origin' => [
                    'lat' => $ride->origin_lat,
                    'lng' => $ride->origin_lng,
                    'address' => $ride->origin_address,
                ],
                'destination' => [
                    'lat' => $ride->destination_lat,
                    'lng' => $ride->destination_lng,
                    'address' => $ride->destination_address,
                ],
                'seats' => $validated['seats'],
                'ride_type' => $ride->ride_type,
                'vehicle_type' => $ride->vehicle?->type,
                'departure_time' => $ride->departure_time?->toIso8601String(),
            ];
            $aiResult = $this->aiPredictionService->predictPrice($aiPayload);
            $totalPrice = $aiResult['predicted_price'] ?? ($ride->price_per_seat * $validated['seats']);
            $corridor = null;
        }

        if (! $ride->isBus() && $this->rideCategoryTransitionService->isTripCategory($ride)) {
            $trip = $this->rideCategoryTransitionService->createTripFromRideSelection($user, $ride, [
                'pickup_address' => $validated['pickup_address'],
                'dropoff_address' => $validated['dropoff_address'],
                'seats' => $validated['seats'],
                'total_price' => $totalPrice,
            ]);

            if ($ride->driver) {
                $this->mobileNotificationService->sendRideRequestToDriver($trip, $ride->driver);
            }

            return response()->json([
                'success' => true,
                'message' => 'Trip request created successfully',
                'data' => [
                    'id' => $trip->id,
                    'status' => $trip->status,
                    'hours_to_departure' => round(now()->diffInMinutes($ride->departure_time, false) / 60, 2),
                    'travel_type' => 'TRIP',
                ],
            ], 201);
        }

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

        $this->mobileNotificationService->sendBookingRequestToDriver($booking->loadMissing('ride.driver'));

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
                'travel_type' => $ride->isBus()
                    ? 'BOOKING'
                    : (now()->diffInHours($ride->departure_time, false) <= (int) config('ride.booking_to_trip_threshold_hours', self::DEFAULT_TICKET_THRESHOLD_HOURS)
                        ? 'TRIP'
                        : 'BOOKING'),
            ],
        ], 201);
    }

    /**
     * Show ride details for passenger.
     * GET /api/v1/passenger/rides/{id}
     */
    public function showRide(int $id): JsonResponse
    {
        $ride = Ride::with(['driver.user', 'vehicle', 'corridor', 'route.corridor'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->ridePayload($ride),
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

        if (strtoupper((string) $booking->status) === 'CANCELLED') {
            return response()->json([
                'success' => false,
                'message' => 'Booking is already cancelled',
            ], 400);
        }

        if (strtoupper((string) $booking->status) === 'CONFIRMED') {
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
        $query = Ride::with(['driver.user', 'vehicle', 'corridor', 'route.corridor']);

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
            'data' => $rides->map(fn (Ride $ride) => $this->ridePayload($ride)),
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
        $ride = Ride::with(['driver.user', 'vehicle', 'bookings.user', 'reviews', 'corridor', 'route.corridor'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => array_merge(
                $this->ridePayload($ride),
                [
                    'bookings_count' => $ride->bookings->count(),
                    'reviews_count' => $ride->reviews->count(),
                ]
            ),
        ]);
    }

    private function ridePayload(Ride $ride): array
    {
        return [
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
            'corridor' => $this->corridorPayload($ride->corridor),
            'route' => $this->routePayload($ride->route),
            'bus_number' => $ride->bus_number,
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
            'arrival_time_estimated' => $ride->arrival_time_estimated?->toIso8601String(),
            'available_seats' => $ride->available_seats,
            'price_per_seat' => $ride->price_per_seat,
            'currency' => $ride->currency,
            'transport_type' => $ride->transport_type,
            'travel_mode' => $ride->travel_mode,
            'status' => $ride->status,
            'ride_type' => $ride->ride_type,
            'luggage_allowed' => $ride->luggage_allowed,
            'pets_allowed' => $ride->pets_allowed,
            'smoking_allowed' => $ride->smoking_allowed,
            'description' => $ride->description,
            'created_at' => $ride->created_at?->toIso8601String(),
            'ride_rules' => RidePolicy::toApiRules($ride),
        ];
    }

    private function corridorPayload(?Corridor $corridor): ?array
    {
        if (! $corridor) {
            return null;
        }

        return [
            'id' => $corridor->id,
            'code' => $corridor->code,
            'name' => $corridor->name,
            'kinyarwanda_name' => $corridor->kinyarwanda_name,
        ];
    }

    private function routePayload(?TransportRoute $route): ?array
    {
        if (! $route) {
            return null;
        }

        return [
            'id' => $route->id,
            'code' => $route->route_code,
            'name' => $route->name,
            'via' => $route->via,
            'origin' => $route->origin,
            'destination' => $route->destination,
        ];
    }
}
