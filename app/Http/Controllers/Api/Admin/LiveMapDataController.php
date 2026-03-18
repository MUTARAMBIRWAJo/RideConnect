<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Ride;
use Illuminate\Http\JsonResponse;

class LiveMapDataController extends Controller
{
    public function index(): JsonResponse
    {
        $drivers = Driver::query()
            ->with('user:id,name')
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->get(['id', 'user_id', 'current_latitude', 'current_longitude', 'status'])
            ->map(fn (Driver $driver) => [
                'id' => (int) $driver->id,
                'name' => (string) ($driver->user?->name ?? ('Driver #' . $driver->id)),
                'latitude' => (float) $driver->current_latitude,
                'longitude' => (float) $driver->current_longitude,
                'status' => strtolower((string) ($driver->status ?? 'offline')),
            ])
            ->values();

        $rides = Ride::query()
            ->whereNotNull('origin_lat')
            ->whereNotNull('origin_lng')
            ->whereNotNull('destination_lat')
            ->whereNotNull('destination_lng')
            ->latest('id')
            ->limit(2500)
            ->get(['id', 'origin_lat', 'origin_lng', 'destination_lat', 'destination_lng', 'status'])
            ->map(fn (Ride $ride) => [
                'id' => (int) $ride->id,
                'pickup_lat' => (float) $ride->origin_lat,
                'pickup_lng' => (float) $ride->origin_lng,
                'dropoff_lat' => (float) $ride->destination_lat,
                'dropoff_lng' => (float) $ride->destination_lng,
                'status' => (string) ($ride->status ?? 'unknown'),
            ])
            ->values();

        return response()->json([
            'drivers' => $drivers,
            'rides' => $rides,
            'meta' => [
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
