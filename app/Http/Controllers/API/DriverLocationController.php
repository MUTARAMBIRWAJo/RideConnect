<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverLocation;
use App\Models\MobileUser;
use App\Models\Trip;
use App\Services\AITrainingDataLogger;
use App\Services\Location\DriverLocationService;
use App\Services\SupabaseRealtimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class DriverLocationController extends Controller
{
    public function __construct(
        private readonly AITrainingDataLogger $trainingDataLogger,
        private readonly DriverLocationService $locationService,
        private readonly SupabaseRealtimeService $supabase,
    ) {}

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'speed_kmh' => 'nullable|numeric|min:0|max:200',
            'heading' => 'nullable|numeric|between:0,360',
            'accuracy' => 'nullable|numeric|min:0|max:1000',
        ]);

        $mobileUserId = $this->mobileUserId($request);
        $driver = Driver::query()->where('user_id', $mobileUserId)->firstOrFail();

        $location = DriverLocation::query()->updateOrCreate(
            ['driver_id' => $mobileUserId],
            [
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'speed_kmh' => $validated['speed_kmh'] ?? null,
                'heading' => $validated['heading'] ?? null,
                'accuracy' => $validated['accuracy'] ?? null,
                'is_online' => true,
                'last_activity_at' => now(),
            ],
        );
        $location->forceFill(['updated_at' => now()])->save();

        $driver->update([
            'current_latitude' => $validated['latitude'],
            'current_longitude' => $validated['longitude'],
            'last_online_at' => now(),
        ]);

        $activeTrip = Trip::query()
            ->where('driver_id', $driver->id)
            ->whereIn('status', ['accepted', 'enroute_to_pickup', 'arrived_at_pickup', 'in_progress'])
            ->latest('updated_at')
            ->first();

        if ($activeTrip) {
            $this->supabase->broadcast("trip:{$activeTrip->id}", 'driver_location_update', [
                'event' => 'driver_location_update',
                'lat' => (float) $validated['latitude'],
                'lng' => (float) $validated['longitude'],
                'speed_kmh' => isset($validated['speed_kmh']) ? (float) $validated['speed_kmh'] : null,
                'heading' => isset($validated['heading']) ? (float) $validated['heading'] : null,
            ]);

            if ($this->shouldPersistTripLocation($mobileUserId) && Schema::hasTable('trip_locations')) {
                DB::table('trip_locations')->insert([
                    'trip_id' => $activeTrip->id,
                    'lat' => $validated['latitude'],
                    'lng' => $validated['longitude'],
                    'speed' => $validated['speed_kmh'] ?? null,
                    'heading' => $validated['heading'] ?? null,
                    'recorded_at' => now(),
                ]);
            }
        }

        $this->trainingDataLogger->logDriverLocation(
            $driver->id,
            (float) $validated['latitude'],
            (float) $validated['longitude'],
        );

        return response()->json([
            'status' => 'success',
            'data' => $location,
        ]);
    }

    public function show(Request $request, int $driver_id): JsonResponse
    {
        return $this->getLocation($request, $driver_id);
    }

    /**
     * Get current location of a driver
     */
    public function getLocation(Request $request, int $driverId): JsonResponse
    {
        $mobileUserDriverId = Driver::query()->where('id', $driverId)->value('user_id') ?: $driverId;
        $location = DriverLocation::query()->where('driver_id', $mobileUserDriverId)->first();

        if (! $location) {
            return response()->json([
                'success' => false,
                'message' => 'Driver location not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'driver_id' => (int) $location->driver_id,
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'speed_kmh' => (float) $location->speed_kmh,
                'heading' => (float) $location->heading,
                'accuracy' => (float) $location->accuracy,
                'is_online' => (bool) $location->is_online,
                'updated_at' => $location->updated_at?->toIso8601String(),
                'last_activity_at' => $location->last_activity_at?->toIso8601String(),
            ],
        ]);
    }

    private function shouldPersistTripLocation(int $mobileUserId): bool
    {
        try {
            $count = Redis::incr("driver_location_ping:{$mobileUserId}");
            Redis::expire("driver_location_ping:{$mobileUserId}", 3600);

            return $count % 3 === 0;
        } catch (\Throwable) {
            return now()->second % 3 === 0;
        }
    }

    private function mobileUserId(Request $request): int
    {
        $user = $request->user();
        if ($user->mobile_user_id) {
            return (int) $user->mobile_user_id;
        }

        return (int) (MobileUser::query()->where('email', $user->email)->value('id') ?? $user->id);
    }

    /**
     * Get nearby online drivers
     */
    public function getNearbyDrivers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_km' => 'nullable|numeric|min:0.1|max:50',
        ]);

        $drivers = $this->locationService->getNearbyDrivers(
            latitude: (float) $validated['latitude'],
            longitude: (float) $validated['longitude'],
            radiusKm: $validated['radius_km'] ?? 5.0
        );

        return response()->json([
            'success' => true,
            'data' => $drivers,
        ]);
    }
}
