<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\MotorcycleTrip;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminMatchingMetricsController extends Controller
{
    /**
     * GET /api/v1/admin/live-requests
     *
     * Returns aggregate matching metrics for the admin dashboard.
     * Authenticated via sanctum manager token (same as other admin routes).
     */
    public function liveRequests(): JsonResponse
    {
        $activeQuery = MotorcycleTrip::query()
            ->whereIn('status', ['REQUESTED', 'MATCHING', 'MATCHING_PENDING', 'ASSIGNED', 'DRIVER_ASSIGNED', 'PASSENGER_WAITING', 'IN_PROGRESS']);

        $expiredQuery = MotorcycleTrip::query()
            ->where('status', 'EXPIRED');

        $completedQuery = MotorcycleTrip::query()
            ->where('status', 'COMPLETED');

        $activeCount = (clone $activeQuery)->count();
        $expiredCount = (clone $expiredQuery)->count();
        $completedCount = (clone $completedQuery)->count();

        $avgMatchingDuration = MotorcycleTrip::query()
            ->whereNotNull('matching_duration_seconds')
            ->avg('matching_duration_seconds');

        $avgByTransport = MotorcycleTrip::query()
            ->whereNotNull('matching_duration_seconds')
            ->selectRaw('
                COUNT(*) as count,
                AVG(matching_duration_seconds) as avg_duration,
                MIN(matching_duration_seconds) as min_duration,
                MAX(matching_duration_seconds) as max_duration
            ')
            ->first();

        $recentlyExpired = MotorcycleTrip::query()
            ->where('status', 'EXPIRED')
            ->where('updated_at', '>=', now()->subHours(1))
            ->count();

        $recentlyCompleted = MotorcycleTrip::query()
            ->where('status', 'COMPLETED')
            ->where('completed_at', '>=', now()->subHours(1))
            ->count();

        $onlineDrivers = Driver::query()
            ->whereIn('availability_status', ['online', 'available'])
            ->where('is_available', true)
            ->count();

        $busyDrivers = Driver::query()
            ->where('availability_status', 'busy')
            ->orWhereNotNull('current_trip_id')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'active_requests' => $activeCount,
                'expired_requests' => $expiredCount,
                'completed_requests' => $completedCount,
                'average_matching_seconds' => $avgMatchingDuration ? round((float) $avgMatchingDuration, 1) : null,
                'matching_batch' => [
                    'count' => (int) $avgByTransport->count ?? 0,
                    'avg_duration_seconds' => $avgByTransport->avg_duration ? round((float) $avgByTransport->avg_duration, 1) : null,
                    'min_duration_seconds' => $avgByTransport->min_duration ?? null,
                    'max_duration_seconds' => $avgByTransport->max_duration ?? null,
                ],
                'last_hour' => [
                    'expired' => $recentlyExpired,
                    'completed' => $recentlyCompleted,
                ],
                'drivers' => [
                    'online_available' => $onlineDrivers,
                    'busy' => $busyDrivers,
                ],
            ],
        ]);
    }
}
