<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Ride;
use App\Models\SeatReservation;
use App\Models\TransportTicket;
use App\Models\Trip;
use App\Services\PublicTransportAvailabilityService;
use App\Services\TransportTicketService;
use App\Services\TripAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicTransportController extends Controller
{
    public function __construct(
        private readonly PublicTransportAvailabilityService $availabilityService,
        private readonly TripAssignmentService $assignmentService,
        private readonly TransportTicketService $ticketService,
    ) {}

    public function available(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'transport_type' => 'nullable|in:BUS,MOTORCYCLE,bus,motorcycle,moto,MOTO',
            'route_id' => 'nullable|integer|exists:transport_routes,id',
        ]);

        if (($filters['transport_type'] ?? null) === 'MOTO') {
            $filters['transport_type'] = Ride::TRANSPORT_MOTORCYCLE;
        }
        if (($filters['transport_type'] ?? null) === 'moto') {
            $filters['transport_type'] = Ride::TRANSPORT_MOTORCYCLE;
        }

        $rides = $this->availabilityService->availableQuery($filters)
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rides->map(fn (Ride $ride): array => [
                'ride_id' => $ride->id,
                'transport_type' => $ride->transport_type,
                'route_id' => $ride->route_id,
                'route_name' => $ride->route?->name,
                'origin' => $ride->origin_address,
                'destination' => $ride->destination_address,
                'available_seats' => (int) $ride->available_seats,
                'departure_time' => $ride->departure_time?->toIso8601String(),
                'price_per_seat' => (float) $ride->price_per_seat,
                'driver' => [
                    'id' => $ride->driver_id,
                    'name' => $ride->driver?->user?->name,
                    'availability_status' => $ride->driver?->availability_status,
                    'rating' => $ride->driver?->rating,
                ],
                'vehicle' => [
                    'id' => $ride->vehicle_id,
                    'type' => $ride->vehicle?->vehicle_type,
                    'plate' => $ride->vehicle?->license_plate,
                    'maintenance_status' => $ride->vehicle?->maintenance_status,
                ],
            ])->values(),
        ]);
    }

    public function createTripRequest(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'ride_id' => 'required|integer|exists:rides,id',
            'requested_seats' => 'nullable|integer|min:1|max:8',
            'pickup_location' => 'required|string|max:255',
            'pickup_lat' => 'required|numeric|between:-90,90',
            'pickup_lng' => 'required|numeric|between:-180,180',
            'dropoff_location' => 'required|string|max:255',
            'dropoff_lat' => 'required|numeric|between:-90,90',
            'dropoff_lng' => 'required|numeric|between:-180,180',
        ]);

        try {
            $trip = DB::transaction(function () use ($validated, $user): Trip {
                $ride = Ride::query()->with('driver')->lockForUpdate()->findOrFail((int) $validated['ride_id']);
                $requestedSeats = (int) ($validated['requested_seats'] ?? 1);
                $passengerId = $user->mobile_user_id ?: null;

                if (! in_array($ride->transport_type, [Ride::TRANSPORT_BUS, Ride::TRANSPORT_MOTORCYCLE], true)) {
                    throw DomainException::make('Only bus and moto public transport are supported now', 'PUBLIC_TRANSPORT_ONLY');
                }

                if (! $this->availabilityService->availableQuery(['transport_type' => $ride->transport_type])
                    ->whereKey($ride->id)
                    ->exists()) {
                    throw DomainException::make('Selected transport is no longer available', 'TRANSPORT_UNAVAILABLE');
                }

                $booking = null;
                $seatReservation = null;
                if ($ride->transport_type === Ride::TRANSPORT_BUS) {
                    if ($ride->available_seats < $requestedSeats) {
                        throw DomainException::make('Insufficient seats', 'INSUFFICIENT_SEATS');
                    }

                    $ride->decrement('available_seats', $requestedSeats);

                    $booking = Booking::query()->create([
                        'user_id' => $user->id,
                        'ride_id' => $ride->id,
                        'seats_booked' => $requestedSeats,
                        'total_price' => $ride->price_per_seat * $requestedSeats,
                        'currency' => $ride->currency,
                        'status' => 'CONFIRMED',
                        'pickup_address' => $validated['pickup_location'],
                        'pickup_lat' => $validated['pickup_lat'],
                        'pickup_lng' => $validated['pickup_lng'],
                        'dropoff_address' => $validated['dropoff_location'],
                        'dropoff_lat' => $validated['dropoff_lat'],
                        'dropoff_lng' => $validated['dropoff_lng'],
                        'confirmed_at' => now(),
                    ]);

                    $seatReservation = SeatReservation::query()->create([
                        'ride_id' => $ride->id,
                        'booking_id' => $booking->id,
                        'passenger_id' => $passengerId,
                        'seats' => $requestedSeats,
                        'status' => 'reserved',
                        'reserved_at' => now(),
                    ]);
                }

                $trip = Trip::query()->create([
                    'booking_id' => $booking?->id,
                    'ride_id' => $ride->id,
                    'transport_type' => $ride->transport_type,
                    'passenger_id' => $passengerId,
                    'driver_id' => null,
                    'pickup_location' => $validated['pickup_location'],
                    'pickup_place_name' => $validated['pickup_location'],
                    'pickup_lat' => $validated['pickup_lat'],
                    'pickup_lng' => $validated['pickup_lng'],
                    'dropoff_location' => $validated['dropoff_location'],
                    'dropoff_place_name' => $validated['dropoff_location'],
                    'dropoff_lat' => $validated['dropoff_lat'],
                    'dropoff_lng' => $validated['dropoff_lng'],
                    'fare' => $ride->price_per_seat * $requestedSeats,
                    'status' => 'PENDING',
                    'payment_status' => 'pending',
                    'assignment_status' => 'unassigned',
                    'requested_at' => now(),
                ]);

                $seatReservation?->update(['trip_id' => $trip->id]);

                return $trip->fresh(['ride.driver']);
            }, 2);

            if ($trip->ride?->driver_id) {
                $scoreBreakdown = $this->assignmentService->defaultScoreBreakdown($trip, $trip->ride->driver);
                $this->assignmentService->createAttempt(
                    $trip,
                    $trip->ride->driver,
                    $scoreBreakdown
                );
            }
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'trip_id' => $trip->id,
                'status' => $trip->status,
                'assignment_status' => $trip->fresh()->assignment_status,
                'payment_status' => $trip->payment_status,
            ],
        ], 201);
    }

    public function currentTrip(Request $request): JsonResponse
    {
        $trip = Trip::query()
            ->where('passenger_id', $request->user()->mobile_user_id)
            ->whereIn('status', ['PENDING', 'ACCEPTED', 'STARTED'])
            ->latest()
            ->first();

        return response()->json(['success' => true, 'data' => $trip]);
    }

    public function ticket(Request $request, Trip $trip): JsonResponse
    {
        if ((int) $trip->passenger_id !== (int) $request->user()->mobile_user_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $ticket = $trip->transportTicket ?: $this->ticketService->issueForTrip($trip);

        return response()->json([
            'success' => true,
            'data' => [
                'ticket_code' => $ticket->ticket_code,
                'qr_payload' => $ticket->qr_payload,
                'status' => $ticket->status,
                'payment_status' => $ticket->payment_status,
                'issued_at' => $ticket->issued_at?->toIso8601String(),
            ],
        ]);
    }

    public function feedback(Request $request, Trip $trip): JsonResponse
    {
        if ((int) $trip->passenger_id !== (int) $request->user()->mobile_user_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        $review = Review::query()->updateOrCreate(
            ['booking_id' => $trip->booking_id, 'user_id' => $request->user()->id],
            [
                'driver_id' => $trip->driver_id,
                'ride_id' => $trip->ride_id,
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                'reviewer_type' => 'passenger',
                'is_public' => false,
            ]
        );

        return response()->json(['success' => true, 'data' => $review]);
    }
}
