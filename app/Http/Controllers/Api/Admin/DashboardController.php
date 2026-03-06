<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\TracksAdminActivity;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminActivityResource;
use App\Http\Resources\Admin\AdminDashboardAnalyticsResource;
use App\Http\Resources\Admin\SystemLogResource;
use App\Models\Manager;
use App\Models\User;
use App\Models\Trip;
use App\Models\Payment;
use App\Models\Ride;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class DashboardController extends Controller
{
    use TracksAdminActivity;

    private const TICKET_THRESHOLD_HOURS = 6;

    /**
     * Get dashboard statistics.
     * GET /api/v1/admin/dashboard
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        Gate::forUser($user)->authorize('viewAdminDashboard', Manager::class);

        $validated = Validator::make($request->all(), [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'type' => ['nullable', 'string', 'in:ticket,booking'],
        ])->validate();

        // Time range filter
        $startDate = isset($validated['start_date']) ? Carbon::parse($validated['start_date'])->startOfDay() : now()->startOfMonth();
        $endDate = isset($validated['end_date']) ? Carbon::parse($validated['end_date'])->endOfDay() : now()->endOfMonth();

        // User statistics
        $totalUsers = User::count();
        $totalDrivers = User::where('role', 'DRIVER')->count();
        $totalPassengers = User::where('role', 'PASSENGER')->count();
        $totalManagers = User::whereIn('role', ['SUPER_ADMIN', 'ADMIN', 'ACCOUNTANT', 'OFFICER'])->count();
        
        // Pending approvals
        $pendingApprovals = User::where('is_approved', false)->count();

        // Trip statistics
        $tripDateColumnForCompleted = Schema::hasColumn('trips', 'completed_at') ? 'completed_at' : 'created_at';
        $tripDateColumnForCancelled = Schema::hasColumn('trips', 'cancelled_at') ? 'cancelled_at' : 'created_at';

        $totalTrips = Trip::whereBetween('created_at', [$startDate, $endDate])->count();
        $completedTrips = Trip::where('status', 'COMPLETED')
            ->whereBetween($tripDateColumnForCompleted, [$startDate, $endDate])->count();
        $cancelledTrips = Trip::where('status', 'CANCELLED')
            ->whereBetween($tripDateColumnForCancelled, [$startDate, $endDate])->count();

        // Ride statistics
        $totalRides = Ride::whereBetween('created_at', [$startDate, $endDate])->count();
        $activeRides = Ride::whereIn('status', ['ACTIVE', 'active', 'IN_PROGRESS', 'in_progress'])->count();

        // Booking statistics
        $bookingsQuery = Booking::whereBetween('created_at', [$startDate, $endDate])->whereHas('ride');

        if (! empty($validated['type'])) {
            $threshold = now()->copy()->addHours(self::TICKET_THRESHOLD_HOURS);

            if ($validated['type'] === 'ticket') {
                $bookingsQuery->whereHas('ride', fn ($rideQuery) => $rideQuery->where('departure_time', '<=', $threshold));
            }

            if ($validated['type'] === 'booking') {
                $bookingsQuery->whereHas('ride', fn ($rideQuery) => $rideQuery->where('departure_time', '>', $threshold));
            }
        }

        $totalBookings = (clone $bookingsQuery)->count();
        $completedBookings = (clone $bookingsQuery)
            ->where('status', 'CONFIRMED')
            ->count();

        // Revenue statistics
        $totalRevenue = Payment::where('status', 'COMPLETED')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');
        
        $todayRevenue = Payment::where('status', 'COMPLETED')
            ->whereDate('created_at', today())
            ->sum('amount');

        // Average trip fare
        $averageFare = 0;

        if (Schema::hasColumn('trips', 'actual_fare')) {
            $averageFare = Trip::where('status', 'COMPLETED')
                ->whereBetween($tripDateColumnForCompleted, [$startDate, $endDate])
                ->avg('actual_fare') ?? 0;
        }

        $this->trackAdminActivity($user, 'view_dashboard_analytics', 'Viewed admin dashboard analytics.');

        return response()->json([
            'success' => true,
            'data' => new AdminDashboardAnalyticsResource([
                'period' => [
                    'start' => $startDate->toIso8601String(),
                    'end' => $endDate->toIso8601String(),
                ],
                'users' => [
                    'total' => $totalUsers,
                    'drivers' => $totalDrivers,
                    'passengers' => $totalPassengers,
                    'managers' => $totalManagers,
                    'pending_approvals' => $pendingApprovals,
                ],
                'trips' => [
                    'total' => $totalTrips,
                    'completed' => $completedTrips,
                    'cancelled' => $cancelledTrips,
                ],
                'rides' => [
                    'total' => $totalRides,
                    'active' => $activeRides,
                ],
                'bookings' => [
                    'total' => $totalBookings,
                    'completed' => $completedBookings,
                    'type_filter' => isset($validated['type']) ? strtoupper((string) $validated['type']) : null,
                ],
                'revenue' => [
                    'total' => round($totalRevenue, 2),
                    'today' => round($todayRevenue, 2),
                    'average_fare' => round($averageFare, 2),
                    'currency' => 'RWF',
                ],
            ]),
        ]);
    }

    /**
     * Get system logs.
     * GET /api/v1/admin/logs
     */
    public function systemLogs(Request $request): JsonResponse
    {
        $user = $request->user();

        Gate::forUser($user)->authorize('viewSystemLogs', Manager::class);

        $validated = Validator::make($request->all(), [
            'user_id' => ['nullable', 'integer'],
            'action' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ])->validate();

        if (!Schema::hasTable('activity_logs')) {
            return response()->json([
                'success' => true,
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 0,
                    'total' => 0,
                    'last_page' => 1,
                ],
            ]);
        }

        $query = DB::table('activity_logs')->orderByDesc('created_at');

        if (isset($validated['user_id'])) {
            $query->where('manager_id', $validated['user_id']);
        }

        if (!empty($validated['action'])) {
            $query->where('action', 'like', "%{$validated['action']}%");
        }

        if (!empty($validated['start_date'])) {
            $query->whereDate('created_at', '>=', $validated['start_date']);
        }
        if (!empty($validated['end_date'])) {
            $query->whereDate('created_at', '<=', $validated['end_date']);
        }

        $perPage = $validated['per_page'] ?? 50;
        $logs = $query->paginate($perPage);

        $this->trackAdminActivity($user, 'view_system_logs', 'Viewed system logs endpoint.');

        return response()->json([
            'success' => true,
            'data' => SystemLogResource::collection(collect($logs->items())),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    /**
     * Get recent activity.
     * GET /api/v1/admin/activity
     */
    public function recentActivity(Request $request): JsonResponse
    {
        $user = $request->user();
        Gate::forUser($user)->authorize('viewAdminDashboard', Manager::class);

        $validated = Validator::make($request->all(), [
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ])->validate();

        $limit = $validated['limit'] ?? 20;

        // Get recent trips
        $recentTrips = Trip::with(['passenger.user', 'driver.user'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($trip) => [
                'type' => 'trip',
                'id' => $trip->id,
                'status' => $trip->status,
                'passenger' => $trip->passenger?->user?->name,
                'driver' => $trip->driver?->user?->name,
                'fare' => $trip->fare,
                'created_at' => $trip->created_at->toIso8601String(),
            ]);

        // Get recent payments
        $recentPayments = Payment::with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($payment) => [
                'type' => 'payment',
                'id' => $payment->id,
                'amount' => $payment->amount,
                'status' => $payment->status,
                'user' => $payment->user?->name,
                'created_at' => $payment->created_at->toIso8601String(),
            ]);

        // Merge and sort by date
        $activities = $recentTrips->concat($recentPayments)
            ->sortByDesc('created_at')
            ->take($limit)
            ->values();

        $this->trackAdminActivity($user, 'view_recent_activity', 'Viewed recent admin activity feed.');

        return response()->json([
            'success' => true,
            'data' => AdminActivityResource::collection($activities),
        ]);
    }
}
