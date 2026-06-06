<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\MobileUser;
use App\Models\PassengerRouteBoarding;
use App\Models\TransportCorridor;
use App\Services\PublicBusTransportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PassengerPublicBusController extends Controller
{
    public function __construct(private readonly PublicBusTransportService $busService) {}

    public function corridors(): JsonResponse
    {
        $corridors = $this->busService->corridors()->map(function (TransportCorridor $corridor): array {
            return [
                'id' => $corridor->id,
                'corridor_code' => $corridor->corridor_code,
                'corridor_name' => $corridor->corridor_name,
                'transport_type' => $corridor->transport_type,
                'status' => $corridor->status,
                'estimated_duration_minutes' => $corridor->estimated_duration_minutes,
                'start_stop' => $corridor->start_stop_id,
                'end_stop' => $corridor->end_stop_id,
                'stops_count' => $corridor->stops->count(),
            ];
        });

        return response()->json(['success' => true, 'data' => $corridors]);
    }

    public function stops(TransportCorridor $corridor): JsonResponse
    {
        $corridor->load(['stops' => fn ($query) => $query->orderBy('stop_order'), 'stopTimes']);

        return response()->json([
            'success' => true,
            'data' => [
                'corridor' => [
                    'id' => $corridor->id,
                    'corridor_code' => $corridor->corridor_code,
                    'corridor_name' => $corridor->corridor_name,
                ],
                'stops' => $corridor->stops->map(fn ($stop): array => [
                    'id' => $stop->id,
                    'stop_name' => $stop->stop_name,
                    'stop_order' => $stop->stop_order,
                    'latitude' => $stop->latitude,
                    'longitude' => $stop->longitude,
                    'is_major_terminal' => $stop->is_major_terminal,
                    'status' => $stop->status,
                ])->values(),
            ],
        ]);
    }

    public function activeBuses(Request $request, TransportCorridor $corridor): JsonResponse
    {
        $validated = $request->validate([
            'boarding_stop_id' => 'nullable|integer|exists:corridor_stops,id',
            'destination_stop_id' => 'nullable|integer|exists:corridor_stops,id',
        ]);

        $boardingStop = isset($validated['boarding_stop_id'])
            ? $corridor->stops()->whereKey((int) $validated['boarding_stop_id'])->first()
            : null;

        return response()->json([
            'success' => true,
            'data' => $this->busService->activeBuses($corridor, $boardingStop),
        ]);
    }

    public function bookSeat(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user?->isPassenger()) {
            return response()->json([
                'success' => false,
                'message' => 'Only passengers can book bus seats',
                'error_code' => 'PASSENGER_ONLY',
            ], 403);
        }

        if (! $user->is_approved) {
            return response()->json([
                'success' => false,
                'message' => 'Your account must be approved to book a bus seat',
                'error_code' => 'PASSENGER_NOT_APPROVED',
            ], 403);
        }

        try {
            Gate::authorize('create', PassengerRouteBoarding::class);
        } catch (AuthorizationException) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to book a bus seat',
                'error_code' => 'BUS_BOOKING_FORBIDDEN',
            ], 403);
        }

        $validated = $request->validate([
            'corridor_id' => 'required|integer|exists:transport_corridors,id',
            'boarding_stop_id' => 'required|integer|exists:corridor_stops,id',
            'destination_stop_id' => 'required|integer|exists:corridor_stops,id',
            'bus_route_assignment_id' => 'nullable|integer|exists:bus_route_assignments,id',
            'seats_reserved' => 'nullable|integer|min:1|max:8',
        ]);

        try {
            $boarding = $this->busService->bookSeat($request->user(), $validated);
        } catch (DomainException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'error_code' => $exception->getErrorCode(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $this->boardingResponse($boarding),
        ], 201);
    }

    public function currentTrip(Request $request): JsonResponse
    {
        $passengerId = $this->resolvePassengerMobileUserId($request->user());
        $boarding = $this->busService->currentTripForPassenger($passengerId);

        return response()->json([
            'success' => true,
            'data' => $boarding ? $this->boardingResponse($boarding) : null,
        ]);
    }

    public function ticket(Request $request, string $ticket): JsonResponse
    {
        $passengerId = $this->resolvePassengerMobileUserId($request->user());
        $boarding = $this->busService->boardingTicket($ticket, $passengerId);

        return response()->json([
            'success' => true,
            'data' => $this->boardingResponse($boarding),
        ]);
    }

    /**
     * POST /api/v1/passenger/public-bus/request
     * Request a public bus trip with smart matching.
     * 
     * Accepts corridor_id, pickup_location (name), dropoff_location (name).
     * Automatically geocodes locations and finds nearest active bus.
     */
    public function requestTrip(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user?->isPassenger()) {
            return response()->json([
                'success' => false,
                'message' => 'Only passengers can request public bus trips',
            ], 403);
        }

        if (! $user->is_approved) {
            return response()->json([
                'success' => false,
                'message' => 'Your account must be approved to request a bus trip',
            ], 403);
        }

        $validated = $request->validate([
            'corridor_id' => 'required|integer|exists:transport_corridors,id',
            'pickup_location' => 'required|string|min:3|max:255',
            'dropoff_location' => 'required|string|min:3|max:255',
        ]);

        try {
            /** @var \App\Services\PublicBusMatchingService $matchingService */
            $matchingService = app(\App\Services\PublicBusMatchingService::class);
            $result = $matchingService->requestTrip($user, $validated);

            return response()->json($result, 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'MATCHING_FAILED',
            ], 422);
        }
    }

    /**
     * GET /api/v1/passenger/public-bus/requests/{id}
     * Get trip request details and current status.
     */
    public function showRequest(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        if (! $user?->isPassenger()) {
            return response()->json([
                'success' => false,
                'message' => 'Only passengers can view trip requests',
            ], 403);
        }

        // Load trip request
        $tripRequest = \App\Models\TripRequest::query()
            ->where('id', $id)
            ->where('passenger_id', $user->id)
            ->with(['passenger', 'corridor', 'driver.user', 'vehicle'])
            ->firstOrFail();

        try {
            /** @var \App\Services\PublicBusMatchingService $matchingService */
            $matchingService = app(\App\Services\PublicBusMatchingService::class);
            $result = $matchingService->getRequest($tripRequest);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    private function boardingResponse(PassengerRouteBoarding $boarding): array
    {
        $boarding->loadMissing(['trip', 'corridor', 'boardingStop', 'destinationStop', 'busRouteAssignment.bus.driver.user']);

        return [
            'id' => $boarding->id,
            'ticket_code' => $boarding->ticket_code,
            'corridor' => [
                'id' => $boarding->corridor?->id,
                'corridor_code' => $boarding->corridor?->corridor_code,
                'corridor_name' => $boarding->corridor?->corridor_name,
            ],
            'bus_route_assignment_id' => $boarding->bus_route_assignment_id,
            'bus' => [
                'id' => $boarding->busRouteAssignment?->bus?->id,
                'display_name' => trim(sprintf('%s %s %s', $boarding->busRouteAssignment?->bus?->year, $boarding->busRouteAssignment?->bus?->make, $boarding->busRouteAssignment?->bus?->model)),
                'plate' => $boarding->busRouteAssignment?->bus?->license_plate,
                'driver' => $boarding->busRouteAssignment?->driver?->user?->name,
            ],
            'boarding_stop' => [
                'id' => $boarding->boardingStop?->id,
                'stop_name' => $boarding->boardingStop?->stop_name,
                'stop_order' => $boarding->boardingStop?->stop_order,
                'latitude' => $boarding->boardingStop?->latitude,
                'longitude' => $boarding->boardingStop?->longitude,
            ],
            'destination_stop' => [
                'id' => $boarding->destinationStop?->id,
                'stop_name' => $boarding->destinationStop?->stop_name,
                'stop_order' => $boarding->destinationStop?->stop_order,
                'latitude' => $boarding->destinationStop?->latitude,
                'longitude' => $boarding->destinationStop?->longitude,
            ],
            'seats_reserved' => $boarding->seats_reserved,
            'fare_amount' => (float) $boarding->fare_amount,
            'payment_status' => $boarding->payment_status,
            'status' => $boarding->status,
            'boarded_at' => $boarding->boarded_at?->toIso8601String(),
            'completed_at' => $boarding->completed_at?->toIso8601String(),
            'trip' => [
                'id' => $boarding->trip?->id,
                'status' => $boarding->trip?->status,
                'payment_status' => $boarding->trip?->payment_status,
            ],
            'ticket_qr' => $boarding->qr_payload,
        ];
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
