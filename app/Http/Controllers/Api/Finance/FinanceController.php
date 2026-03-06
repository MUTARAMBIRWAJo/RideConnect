<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Trip;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    private const TICKET_THRESHOLD_HOURS = 6;

    /**
     * Get revenue summary.
     * GET /api/v1/finance/summary
     */
    public function revenueSummary(Request $request): JsonResponse
    {
        $user = $request->user();

        // Time range filter
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());
        $bookingType = $request->get('booking_type');

        $baseQuery = Payment::whereBetween('created_at', [$startDate, $endDate]);
        $this->applyBookingTypeFilter($baseQuery, $bookingType);

        // Total revenue
        $totalRevenue = (clone $baseQuery)
            ->whereRaw('LOWER(status) = ?', ['completed'])
            ->sum('amount');

        // Revenue by payment method
        $revenueByMethod = (clone $baseQuery)
            ->whereRaw('LOWER(status) = ?', ['completed'])
            ->select('payment_method', DB::raw('SUM(amount) as total'))
            ->groupBy('payment_method')
            ->get();

        // Revenue split by booking travel type (BOOKING vs TICKET)
        $revenueByType = (clone $baseQuery)
            ->whereRaw('LOWER(status) = ?', ['completed'])
            ->with(['booking.ride'])
            ->get();

        $revenueByType = $revenueByType
            ->groupBy(fn (Payment $payment) => $this->resolveTravelTypeByPayment($payment))
            ->map(fn ($payments, $type) => [
                'type' => $type,
                'total' => collect($payments)->sum('amount'),
            ])
            ->values();

        // Daily revenue
        $dailyRevenue = (clone $baseQuery)
            ->whereRaw('LOWER(status) = ?', ['completed'])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        // Transaction counts
        $totalTransactions = (clone $baseQuery)->count();
        $completedTransactions = (clone $baseQuery)
            ->whereRaw('LOWER(status) = ?', ['completed'])
            ->count();
        $pendingTransactions = (clone $baseQuery)
            ->whereRaw('LOWER(status) = ?', ['pending'])
            ->count();
        $failedTransactions = (clone $baseQuery)
            ->whereRaw('LOWER(status) = ?', ['failed'])
            ->count();

        // Average transaction value
        $averageTransactionValue = $completedTransactions > 0 
            ? $totalRevenue / $completedTransactions 
            : 0;

        // Trip earnings for drivers
        $totalDriverEarnings = Trip::where('status', 'COMPLETED')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->sum('actual_fare');

        // Platform revenue (revenue - driver earnings)
        $platformRevenue = $totalRevenue - $totalDriverEarnings;

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate,
                    'booking_type' => $bookingType ? strtoupper((string) $bookingType) : null,
                ],
                'summary' => [
                    'total_revenue' => round($totalRevenue, 2),
                    'platform_revenue' => round($platformRevenue, 2),
                    'driver_earnings' => round($totalDriverEarnings, 2),
                    'average_transaction' => round($averageTransactionValue, 2),
                    'currency' => 'RWF',
                ],
                'transactions' => [
                    'total' => $totalTransactions,
                    'completed' => $completedTransactions,
                    'pending' => $pendingTransactions,
                    'failed' => $failedTransactions,
                ],
                'revenue_by_method' => $revenueByMethod->map(fn($item) => [
                    'method' => $item->payment_method,
                    'amount' => round($item->total, 2),
                ]),
                'revenue_by_type' => $revenueByType->map(fn($item) => [
                    'type' => $item['type'],
                    'amount' => round($item['total'], 2),
                ]),
                'daily_revenue' => $dailyRevenue->map(fn($item) => [
                    'date' => $item->date,
                    'amount' => round($item->total, 2),
                ]),
            ],
        ]);
    }

    /**
     * Get transactions.
     * GET /api/v1/finance/transactions
     */
    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();
        $bookingType = $request->get('booking_type');

        $query = Payment::with(['user', 'booking.ride'])
            ->orderBy('created_at', 'desc');

        $this->applyBookingTypeFilter($query, $bookingType);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by payment method
        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        // Filter by legacy payment type column (if present)
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by amount range
        if ($request->has('min_amount')) {
            $query->where('amount', '>=', $request->min_amount);
        }
        if ($request->has('max_amount')) {
            $query->where('amount', '<=', $request->max_amount);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $transactions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $transactions->map(fn($payment) => [
                'id' => $payment->id,
                'user' => [
                    'id' => $payment->user?->id,
                    'name' => $payment->user?->name,
                    'email' => $payment->user?->email,
                ],
                'type' => $payment->type,
                'trip_id' => $payment->trip_id,
                'booking_id' => $payment->booking_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'payment_method' => $payment->payment_method,
                'transaction_id' => $payment->transaction_id,
                'created_at' => $payment->created_at->toIso8601String(),
                'travel_type' => $this->resolveTravelTypeByPayment($payment),
            ]),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'last_page' => $transactions->lastPage(),
            ],
            'filters' => [
                'booking_type' => $bookingType ? strtoupper((string) $bookingType) : null,
            ],
        ]);
    }

    private function applyBookingTypeFilter($query, ?string $bookingType): void
    {
        if (! $bookingType) {
            return;
        }

        $normalizedType = strtolower(trim($bookingType));
        $threshold = now()->copy()->addHours(self::TICKET_THRESHOLD_HOURS);

        if ($normalizedType === 'ticket') {
            $query->whereHas('booking.ride', fn ($rideQuery) => $rideQuery->where('departure_time', '<=', $threshold));
        }

        if ($normalizedType === 'booking') {
            $query->whereHas('booking.ride', fn ($rideQuery) => $rideQuery->where('departure_time', '>', $threshold));
        }
    }

    private function resolveTravelTypeByPayment(Payment $payment): string
    {
        $departure = $payment->booking?->ride?->departure_time;

        if (! $departure) {
            return 'BOOKING';
        }

        $hoursToDeparture = now()->diffInMinutes($departure, false) / 60;

        return $hoursToDeparture <= self::TICKET_THRESHOLD_HOURS ? 'TICKET' : 'BOOKING';
    }
}
