<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MotorcycleTrip;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;

class LiveRequestsController extends Controller
{
    public function index(): JsonResponse
    {
        $tripTrips = Trip::query()
            ->with('passenger:id,first_name,last_name', 'driver.user:id,name')
            ->whereIn('status', ['PENDING', 'ACCEPTED', 'STARTED'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (Trip $t) => [
                'source' => 'trip',
                'trip_id' => $t->id,
                'passenger_name' => trim(($t->passenger?->first_name ?? '').' '.($t->passenger?->last_name ?? '')),
                'driver_name' => $t->driver?->user?->name,
                'pickup_lat' => (float) $t->pickup_lat,
                'pickup_lng' => (float) $t->pickup_lng,
                'dropoff_lat' => (float) $t->dropoff_lat,
                'dropoff_lng' => (float) $t->dropoff_lng,
                'status' => $t->status,
                'assignment_status' => $t->assignment_status,
                'payment_status' => $t->payment_status,
                'fare' => $t->fare ? (float) $t->fare : null,
                'created_at' => $t->created_at?->toIso8601String(),
            ])->values();

        $motoTrips = MotorcycleTrip::query()
            ->with('passenger:id,first_name,last_name', 'driver.user:id,name')
            ->whereIn('status', [
                'REQUESTED', 'MATCHING', 'MATCHING_PENDING',
                'ASSIGNED', 'DRIVER_ASSIGNED', 'PASSENGER_WAITING', 'IN_PROGRESS',
            ])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (MotorcycleTrip $t) => [
                'source' => 'motorcycle',
                'trip_id' => $t->id,
                'passenger_name' => trim(($t->passenger?->first_name ?? '').' '.($t->passenger?->last_name ?? '')),
                'driver_name' => $t->driver?->user?->name,
                'pickup_lat' => (float) $t->pickup_lat,
                'pickup_lng' => (float) $t->pickup_lng,
                'dropoff_lat' => (float) $t->dropoff_lat,
                'dropoff_lng' => (float) $t->dropoff_lng,
                'status' => $t->status,
                'matching_status' => $t->matching_status,
                'assignment_status' => $t->matching_status,
                'payment_status' => $t->payment_status ?? 'pending',
                'fare' => $t->estimated_fare ? (float) $t->estimated_fare : null,
                'search_radius_km' => $t->current_search_radius_km,
                'candidate_count' => $t->candidate_count,
                'created_at' => $t->created_at?->toIso8601String(),
            ])->values();

        $all = $tripTrips->merge($motoTrips)->sortByDesc('created_at')->values();

        return response()->json([
            'success' => true,
            'data' => $all->all(),
            'count' => $all->count(),
        ]);
    }
}
