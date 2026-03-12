<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverLocation;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;

class MapDataController extends Controller
{
    public function index(): JsonResponse
    {
        $driverLocations = DriverLocation::query()->get()->keyBy('driver_id');

        $driversByProfileId = Driver::query()
            ->whereIn('id', $driverLocations->keys())
            ->get(['id', 'user_id'])
            ->keyBy('id');

        $driversByUserId = $driversByProfileId->keyBy('user_id');

        $drivers = $driverLocations
            ->map(function (DriverLocation $location) use ($driversByProfileId) {
                $driverProfile = $driversByProfileId->get($location->driver_id);
                if (!$driverProfile) {
                    return null;
                }

                return [
                    'id' => (int) $driverProfile->user_id,
                    'driver_profile_id' => (int) $driverProfile->id,
                    'lat' => (float) $location->latitude,
                    'lng' => (float) $location->longitude,
                ];
            })
            ->filter()
            ->values();

        $activeTrips = Trip::query()
            ->whereIn('status', ['PENDING', 'ACCEPTED', 'STARTED'])
            ->get(['id', 'driver_id', 'passenger_id', 'pickup_lat', 'pickup_lng', 'status']);

        $activeDriverUserIds = $activeTrips
            ->whereIn('status', ['ACCEPTED', 'STARTED'])
            ->pluck('driver_id')
            ->filter()
            ->unique();

        $trackedDriverCount = $drivers->count();
        $activeRideCount = $activeTrips->whereIn('status', ['ACCEPTED', 'STARTED'])->count();

        $drivers = $drivers
            ->map(function (array $driver) use ($activeDriverUserIds) {
                // A tracked driver location means the driver is currently online for ops view.
                $driver['is_active'] = true;
                $driver['on_ride'] = $activeDriverUserIds->contains($driver['id']);

                return $driver;
            })
            ->values();

        $passengers = $activeTrips
            ->filter(fn (Trip $trip) => $trip->pickup_lat !== null && $trip->pickup_lng !== null)
            ->map(fn (Trip $trip) => [
                'id' => (int) $trip->passenger_id,
                'lat' => (float) $trip->pickup_lat,
                'lng' => (float) $trip->pickup_lng,
            ])
            ->unique('id')
            ->values();

        $rides = $activeTrips
            ->whereIn('status', ['ACCEPTED', 'STARTED'])
            ->filter(function (Trip $trip) use ($driversByUserId) {
                return $trip->driver_id !== null
                    && $trip->pickup_lat !== null
                    && $trip->pickup_lng !== null
                    && $driversByUserId->has($trip->driver_id);
            })
            ->map(function (Trip $trip) use ($driversByUserId, $driverLocations) {
                $driverProfile = $driversByUserId->get($trip->driver_id);
                if (!$driverProfile) {
                    return null;
                }

                $driverLocation = $driverLocations->get($driverProfile->id);
                if (!$driverLocation) {
                    return null;
                }

                return [
                    'id' => (int) $trip->id,
                    'driver_lat' => (float) $driverLocation->latitude,
                    'driver_lng' => (float) $driverLocation->longitude,
                    'passenger_lat' => (float) $trip->pickup_lat,
                    'passenger_lng' => (float) $trip->pickup_lng,
                    'status' => $trip->status,
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'drivers' => $drivers,
            'passengers' => $passengers,
            'rides' => $rides,
            'active_rides' => $rides,
            'active_driver_count' => $trackedDriverCount,
            'active_ride_count' => $activeRideCount,
        ]);
    }
}
