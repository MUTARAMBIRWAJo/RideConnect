<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;

class LiveRequestsController extends Controller
{
    public function index(): JsonResponse
    {
        $requests = Trip::query()
            ->with('passenger:id,first_name,last_name')
            ->where('status', 'PENDING')
            ->whereNotNull('pickup_lat')
            ->whereNotNull('pickup_lng')
            ->whereNotNull('dropoff_lat')
            ->whereNotNull('dropoff_lng')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'requests' => $requests->map(function (Trip $trip) {
                return [
                    'id' => (int) $trip->id,
                    'passenger_name' => trim(($trip->passenger?->first_name ?? '') . ' ' . ($trip->passenger?->last_name ?? '')),
                    'pickup_lat' => (float) $trip->pickup_lat,
                    'pickup_lng' => (float) $trip->pickup_lng,
                    'destination_lat' => (float) $trip->dropoff_lat,
                    'destination_lng' => (float) $trip->dropoff_lng,
                ];
            })->values(),
        ]);
    }
}
