<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ResolvesCanonicalIdentity;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Trip;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    use ResolvesCanonicalIdentity;

    public function __construct(
        private readonly LedgerService $ledgerService,
    ) {}

    /**
     * Create a new payment.
     * POST /api/v1/payments
     */
    public function createPayment(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'type' => 'required|string|in:trip,booking,motor_vehicle',
            'trip_id' => 'required_if:type,trip|nullable|exists:trips,id',
            'motorcycle_trip_id' => 'required_if:type,motor_vehicle|nullable|exists:motorcycle_trips,id',
            'booking_id' => 'required_if:type,booking|nullable|exists:bookings,id',
            'amount' => 'required|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'payment_method' => 'required|string|in:card,cash,mobile_money,bank_transfer',
            'transaction_id' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ]);

        // motor_vehicle payments settle a MotorcycleTrip (separate table from trips).
        if ($validated['type'] === 'motor_vehicle') {
            $motoTrip = \App\Models\MotorcycleTrip::findOrFail($validated['motorcycle_trip_id']);
            if ((int) $motoTrip->passenger_id !== (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only pay for your own trips',
                ], 403);
            }

            $payment = DB::transaction(function () use ($validated, $user, $motoTrip) {
                return Payment::create([
                    'user_id' => $user->id,
                    'type' => 'motor_vehicle',
                    'motorcycle_trip_id' => (int) $motoTrip->id,
                    'amount' => $validated['amount'],
                    'currency' => $validated['currency'] ?? ($motoTrip->currency ?: 'RWF'),
                    'payment_method' => $validated['payment_method'],
                    'transaction_id' => $validated['transaction_id'] ?? null,
                    'status' => 'pending',
                    'metadata' => $validated['metadata'] ?? [],
                ]);
            });

            $motoTrip->forceFill(['actual_fare' => (float) $validated['amount']])->save();

            return response()->json([
                'success' => true,
                'message' => 'Payment initiated successfully',
                'data' => [
                    'id' => $payment->id,
                    'type' => $payment->type,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                    'payment_method' => $payment->payment_method,
                ],
            ], 201);
        }

        // Verify ownership
        $resolvedTripId = null;

        if ($validated['type'] === 'trip') {
            $trip = Trip::findOrFail($validated['trip_id']);
            if ((int) $trip->passenger_id !== (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only pay for your own trips',
                ], 403);
            }

            $resolvedTripId = (int) $trip->id;
        } else {
            $booking = Booking::findOrFail($validated['booking_id']);
            if ($booking->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only pay for your own bookings',
                ], 403);
            }

            $trip = Trip::query()->where('booking_id', $booking->id)->first();

            if (! $trip) {
                $passengerId = $this->passengerOwnerId($booking->user);

                $trip = Trip::create([
                    'booking_id' => $booking->id,
                    'ride_id' => $booking->ride_id,
                    'passenger_id' => $passengerId,
                    'driver_id' => $booking->ride?->driver_id,
                    'pickup_location' => $booking->pickup_address ?: $booking->ride?->origin_address,
                    'pickup_lat' => $booking->pickup_lat ?: $booking->ride?->origin_lat,
                    'pickup_lng' => $booking->pickup_lng ?: $booking->ride?->origin_lng,
                    'dropoff_location' => $booking->dropoff_address ?: $booking->ride?->destination_address,
                    'dropoff_lat' => $booking->dropoff_lat ?: $booking->ride?->destination_lat,
                    'dropoff_lng' => $booking->dropoff_lng ?: $booking->ride?->destination_lng,
                    'fare' => $booking->total_price,
                    'status' => 'PENDING',
                    'requested_at' => $booking->created_at,
                ]);
            }

            $resolvedTripId = (int) $trip->id;
        }

        if (! $resolvedTripId) {
            throw ValidationException::withMessages([
                'trip_id' => 'Unable to resolve trip for this payment request.',
            ]);
        }

        $payment = DB::transaction(function () use ($validated, $user, $resolvedTripId) {
            return Payment::create([
                'user_id' => $user->id,
                'type' => strtolower((string) $validated['type']),
                'trip_id' => $resolvedTripId,
                'booking_id' => $validated['booking_id'] ?? null,
                'amount' => $validated['amount'],
                'currency' => $validated['currency'] ?? 'RWF',
                'payment_method' => $validated['payment_method'],
                'transaction_id' => $validated['transaction_id'] ?? null,
                'status' => 'pending',
                'metadata' => $validated['metadata'] ?? [],
            ]);
        });

        Trip::query()->where('id', $resolvedTripId)->update([
            'actual_fare' => (float) $validated['amount'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment initiated successfully',
            'data' => [
                'id' => $payment->id,
                'type' => $payment->type,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'payment_method' => $payment->payment_method,
            ],
        ], 201);
    }

    /**
     * Get payment history.
     * GET /api/v1/payments/history
     */
    public function paymentHistory(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Payment::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $payments = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $payments->map(fn ($payment) => [
                'id' => $payment->id,
                'type' => $payment->type,
                'trip_id' => $payment->trip_id,
                'booking_id' => $payment->booking_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'payment_method' => $payment->payment_method,
                'transaction_id' => $payment->transaction_id,
                'created_at' => $payment->created_at->toIso8601String(),
            ]),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'last_page' => $payments->lastPage(),
            ],
        ]);
    }

    /**
     * Get payment details.
     * GET /api/v1/payments/{id}
     */
    public function show(int $id): JsonResponse
    {
        $user = request()->user();

        $payment = Payment::where('user_id', $user->id)
            ->with(['trip', 'booking'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $payment->id,
                'type' => $payment->type,
                'trip_id' => $payment->trip_id,
                'booking_id' => $payment->booking_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'payment_method' => $payment->payment_method,
                'transaction_id' => $payment->transaction_id,
                'metadata' => $payment->metadata,
                'created_at' => $payment->created_at->toIso8601String(),
                'updated_at' => $payment->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Process payment callback/webhook.
     * POST /api/v1/payments/webhook
     */
    public function webhook(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transaction_id' => 'required|string',
            'status' => 'required|string|in:completed,failed,pending,refunded',
            'amount' => 'nullable|numeric',
            'metadata' => 'nullable|array',
        ]);

        $payment = Payment::where('transaction_id', $validated['transaction_id'])->first();

        if (! $payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found',
            ], 404);
        }

        $newStatus = strtoupper($validated['status']);

        DB::transaction(function () use ($payment, $validated, $newStatus): void {
            // Update payment status and timestamps first.
            $payment->update([
                'status' => $newStatus,
                'paid_at' => $newStatus === 'COMPLETED' ? now() : $payment->paid_at,
                'refunded_at' => $newStatus === 'REFUNDED' ? now() : $payment->refunded_at,
                'metadata' => array_merge($payment->metadata ?? [], $validated['metadata'] ?? []),
            ]);

            if ($payment->trip_id) {
                $tripUpdates = ['actual_fare' => (float) $payment->amount];

                if ($newStatus === 'COMPLETED') {
                    $tripUpdates['completed_at'] = now();
                }

                Trip::query()->where('id', $payment->trip_id)->update($tripUpdates);
            }

            if ($newStatus === 'COMPLETED') {
                $alreadyPosted = LedgerEntry::query()
                    ->where('reference_type', 'payment')
                    ->where('reference_id', $payment->id)
                    ->exists();

                if (! $alreadyPosted) {
                    $provider = $payment->payment_provider === 'mtn_momo' ? 'mtn_momo' : 'stripe';
                    $this->ledgerService->recordPaymentReceived($payment->fresh(), $provider);
                }
            }

            if ($newStatus === 'REFUNDED') {
                $alreadyPosted = LedgerEntry::query()
                    ->where('reference_type', 'refund')
                    ->where('reference_id', $payment->id)
                    ->exists();

                if (! $alreadyPosted) {
                    $this->ledgerService->recordRefund($payment->fresh());
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Payment status updated',
        ]);
    }
}
