<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Models\Trip;
use App\Services\PaymentVerificationService;
use App\Services\PublicTransportAvailabilityService;
use App\Services\ReassignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfficerPublicTransportController extends Controller
{
    public function __construct(
        private readonly PublicTransportController $publicTransportController,
        private readonly PublicTransportAvailabilityService $availabilityService,
        private readonly ReassignmentService $reassignmentService,
        private readonly PaymentVerificationService $paymentVerificationService,
    ) {}

    public function assistedBooking(Request $request): JsonResponse
    {
        return $this->publicTransportController->createTripRequest($request);
    }

    public function reassign(Request $request, int $trip): JsonResponse
    {
        // Explicit model fetching to handle invalid IDs gracefully (including 0)
        $tripModel = Trip::query()->find($trip);
        if (! $tripModel) {
            return response()->json(['success' => false, 'message' => 'Trip not found'], 404);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        try {
            $attempt = $this->reassignmentService->reassign(
                (int) $tripModel->id,
                $validated['reason'],
                'officer:'.$request->user()->id
            );
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ], 422);
        }

        return response()->json(['success' => true, 'data' => $attempt]);
    }

    public function verifyPayment(Request $request, int $payment): JsonResponse
    {
        $validated = $request->validate([
            'verification_method' => 'nullable|string|max:64',
            'notes' => 'nullable|string|max:2000',
        ]);

        $payment = $this->paymentVerificationService->verify(
            $payment,
            (int) $request->user()->id,
            $validated['verification_method'] ?? 'officer_manual',
            $validated['notes'] ?? null
        );

        return response()->json(['success' => true, 'data' => $payment]);
    }

    public function seatMonitoring(Request $request): JsonResponse
    {
        $rides = Ride::query()
            ->with(['driver.user', 'route', 'seatReservations'])
            ->whereIn('transport_type', [Ride::TRANSPORT_BUS, Ride::TRANSPORT_MOTORCYCLE])
            ->when($request->filled('transport_type'), fn ($query) => $query->where('transport_type', strtoupper((string) $request->transport_type)))
            ->when($request->filled('route_id'), fn ($query) => $query->where('route_id', (int) $request->route_id))
            ->orderBy('departure_time')
            ->limit(100)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rides->map(fn (Ride $ride): array => [
                'ride_id' => $ride->id,
                'transport_type' => $ride->transport_type,
                'route' => $ride->route?->name,
                'available_seats' => (int) $ride->available_seats,
                'reserved_seats' => (int) $ride->seatReservations->where('status', 'reserved')->sum('seats'),
                'consumed_seats' => (int) $ride->seatReservations->where('status', 'consumed')->sum('seats'),
                'driver_status' => $ride->driver?->availability_status,
                'seat_mismatch' => $ride->available_seats < 0,
            ]),
        ]);
    }

    public function available(Request $request): JsonResponse
    {
        return $this->publicTransportController->available($request);
    }
}
