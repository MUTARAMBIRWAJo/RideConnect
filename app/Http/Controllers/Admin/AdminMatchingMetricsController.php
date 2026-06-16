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

    /**
     * GET /api/v1/admin/matching/debug/{tripId}
     *
     * Diagnostic endpoint to evaluate driver matching state for a specific trip.
     */
    public function matchingDebug($tripId): JsonResponse
    {
        $trip = \App\Models\Trip::find($tripId);
        
        if (!$trip) {
            return response()->json(['success' => false, 'message' => 'Trip not found'], 404);
        }

        $distanceSql = '(6371 * acos(cos(radians(?)) * cos(radians(dl.latitude)) * cos(radians(dl.longitude) - radians(?)) + sin(radians(?)) * sin(radians(dl.latitude))))';

        $rejectedDriverIds = DB::table('trip_rejections')->where('trip_id', $tripId)->pluck('driver_id')->all();

        $candidates = DB::table('drivers as d')
            ->join('driver_locations as dl', 'dl.driver_id', '=', 'd.user_id')
            ->selectRaw('d.id, d.status as approval_status, d.availability_status, dl.is_online, dl.last_activity_at, ' . $distanceSql . ' as distance_km', [
                $trip->pickup_lat,
                $trip->pickup_lng,
                $trip->pickup_lat,
            ])
            ->whereNull('d.deleted_at')
            ->havingRaw($distanceSql . ' <= ?', [
                $trip->pickup_lat,
                $trip->pickup_lng,
                $trip->pickup_lat,
                10, // Wider 10km radius for diagnostic purposes
            ])
            ->get();

        $driversFound = $candidates->count();
        $filtered = [];
        $filterReasons = [];

        foreach ($candidates as $c) {
            $reasons = [];
            if ($c->approval_status !== 'approved') {
                $reasons[] = 'approval_status_not_approved';
            }
            if (!in_array($c->availability_status, ['online', 'available'])) {
                $reasons[] = 'availability_status_' . $c->availability_status;
            }
            if (!$c->is_online) {
                $reasons[] = 'gps_is_offline';
            }
            if ($c->last_activity_at < now()->subMinutes(3)->toDateTimeString()) {
                $reasons[] = 'gps_stale';
            }
            if (in_array($c->id, $rejectedDriverIds)) {
                $reasons[] = 'previously_rejected';
            }

            if (!empty($reasons)) {
                $filtered[] = $c->id;
                $filterReasons[$c->id] = $reasons;
            }
        }

        $attempt = \App\Models\TripAssignmentAttempt::where('trip_id', $tripId)->latest()->first();

        return response()->json([
            'success' => true,
            'data' => [
                'trip_id' => $trip->id,
                'matching_status' => $trip->assignment_status,
                'retry_count' => DB::table('trip_rejections')->where('trip_id', $tripId)->count(),
                'drivers_found' => $driversFound,
                'drivers_filtered' => count($filtered),
                'filter_reasons' => $filterReasons,
                'selected_driver' => $trip->driver_id,
                'latest_attempt_status' => $attempt ? $attempt->status : null,
                'ml_version' => $trip->ranker_version,
            ],
        ]);
    }
}
