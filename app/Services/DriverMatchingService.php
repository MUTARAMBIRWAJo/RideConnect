<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\DriverBehavior;
use App\Models\Ride;
use App\Models\TripAssignmentAttempt;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DriverMatchingService
{
    public function __construct(private readonly MatchingSessionService $matchingSessionService) {}
    /**
     * @return array{transport_type: string, drivers: array<int, array<string, mixed>>}
     */
    /**
     * @return array{transport_type: string, matching_session_id: string, response_version: int, generated_at: string, expires_at: string, drivers: array<int, array<string, mixed>>}
     */
    public function match(array $payload, int $passengerId): array
    {
        $transportType = $this->normalizeTransportType((string) $payload['transport_type']);
        $pickupLat = (float) $payload['pickup_lat'];
        $pickupLng = (float) $payload['pickup_lng'];
        $dropoffLat = (float) $payload['dropoff_lat'];
        $dropoffLng = (float) $payload['dropoff_lng'];
        $excludedDriverIds = array_map('intval', $payload['excluded_driver_ids'] ?? []);
        $limit = max(1, min((int) ($payload['limit'] ?? 20), 50));
        $generatedAt = now();
        $expiresAt = $generatedAt->copy()->addSeconds(20);

        $matchingSession = $this->matchingSessionService->createSession([
            'passenger_id' => $passengerId,
            'transport_type' => $transportType,
            'pickup_lat' => $pickupLat,
            'pickup_lng' => $pickupLng,
            'dropoff_lat' => $dropoffLat,
            'dropoff_lng' => $dropoffLng,
        ]);

        $drivers = $this->candidateDrivers($transportType, $pickupLat, $pickupLng, $excludedDriverIds, $limit);
        $routeDistanceKm = $this->haversineDistanceKm($pickupLat, $pickupLng, $dropoffLat, $dropoffLng);

        return [
            'transport_type' => $this->responseTransportType($transportType),
            'matching_session_id' => $matchingSession->matching_session_id,
            'response_version' => $this->nextResponseVersion(),
            'generated_at' => $generatedAt->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
            'drivers' => $drivers
                ->map(fn (Driver $driver): array => $this->driverPayload($driver, $transportType, $routeDistanceKm))
                ->values()
                ->all(),
        ];
    }

    public function normalizeTransportType(string $transportType): string
    {
        return match (strtolower(trim($transportType))) {
            'motor_vehicle', 'motorcycle', 'moto', 'motorbike' => Ride::TRANSPORT_MOTORCYCLE,
            'private_car', 'car', 'private' => Ride::TRANSPORT_CAR,
            default => strtoupper(trim($transportType)),
        };
    }

    public function responseTransportType(string $transportType): string
    {
        return match ($transportType) {
            Ride::TRANSPORT_MOTORCYCLE => 'motor_vehicle',
            Ride::TRANSPORT_CAR => 'private_car',
            default => strtolower($transportType),
        };
    }

    public function activeVehicleFor(Driver $driver, string $transportType): ?Vehicle
    {
        $vehicleTypes = TransportMappingService::getVehicleTypesFor($transportType);

        return $driver->vehicles
            ->first(fn (Vehicle $vehicle): bool => $vehicle->is_active && in_array($vehicle->vehicle_type, $vehicleTypes, true));
    }

    public function estimateFare(string $transportType, float $distanceKm): float
    {
        $baseFare = $transportType === Ride::TRANSPORT_MOTORCYCLE ? 500 : 1500;
        $perKm = $transportType === Ride::TRANSPORT_MOTORCYCLE ? 320 : 900;

        return round($baseFare + ($distanceKm * $perKm), -2);
    }

    /**
     * @return Collection<int, Driver>
     */
    private function candidateDrivers(string $transportType, float $pickupLat, float $pickupLng, array $excludedDriverIds, int $limit): Collection
    {
        $vehicleTypes = TransportMappingService::getVehicleTypesFor($transportType);

        return Driver::query()
            ->with([
                'user:id,name,phone,profile_photo,is_approved,mobile_user_id',
                'vehicles',
                'assignmentAttempts' => fn ($query) => $query->whereIn('status', [TripAssignmentAttempt::STATUS_PENDING, TripAssignmentAttempt::STATUS_NOTIFIED]),
            ])
            ->leftJoin('users', 'drivers.user_id', '=', 'users.id')
            ->leftJoin('driver_locations', function ($join): void {
                $join->on('driver_locations.driver_id', '=', 'drivers.id')
                    ->orOn('driver_locations.driver_id', '=', 'users.mobile_user_id');
            })
            ->where('drivers.status', 'approved')
            ->where('drivers.availability_status', 'available')
            ->whereNotIn('drivers.id', $excludedDriverIds)
            ->whereHas('user', fn ($query) => $query->where('is_approved', true))
            ->whereHas('vehicles', function ($query) use ($vehicleTypes): void {
                $query->where('is_active', true)
                    ->whereIn('vehicle_type', $vehicleTypes);
            })
            ->whereDoesntHave('trips', function ($query): void {
                $query->whereIn('status', ['PENDING', 'ACCEPTED', 'STARTED'])
                    ->orWhere(function ($paymentQuery): void {
                        $paymentQuery->where('status', 'COMPLETED')
                            ->whereNull('paid_to_driver_at');
                    });
            })
            ->whereDoesntHave('assignmentAttempts', function ($query): void {
                $query->whereIn('status', [TripAssignmentAttempt::STATUS_PENDING, TripAssignmentAttempt::STATUS_NOTIFIED])
                    ->where('expires_at', '>', now());
            })
            ->select('drivers.*')
            ->selectRaw($this->distanceSql().' AS distance_km', [$pickupLat, $pickupLng, $pickupLat])
            ->selectRaw('COALESCE(driver_locations.latitude, drivers.current_latitude) AS matched_latitude')
            ->selectRaw('COALESCE(driver_locations.longitude, drivers.current_longitude) AS matched_longitude')
            ->where(function ($query): void {
                $query->whereNotNull('driver_locations.latitude')
                    ->orWhereNotNull('drivers.current_latitude');
            })
            ->orderByDesc(DB::raw('CASE WHEN driver_locations.is_online = true THEN 1 ELSE 0 END'))
            ->orderBy('distance_km')
            ->orderByDesc('drivers.rating')
            ->limit($limit)
            ->get();
    }

    private function driverPayload(Driver $driver, string $transportType, float $routeDistanceKm): array
    {
        $vehicle = $this->activeVehicleFor($driver, $transportType);
        $distanceKm = round((float) ($driver->getAttribute('distance_km') ?? 0), 2);
        $behaviorScore = $this->behaviorScore((int) $driver->id);
        $fare = $this->estimateFare($transportType, $routeDistanceKm);
        $hasActiveAssignment = $driver->assignmentAttempts->isNotEmpty();
        $acceptingRequests = $driver->availability_status === 'available' && $driver->user?->is_approved && ! $hasActiveAssignment;
        $assignmentState = $hasActiveAssignment ? 'locked' : ($driver->availability_status === 'busy' ? 'busy' : 'available');

        $payload = [
            'driver_id' => $driver->id,
            'driver_name' => $driver->user?->name,
            'profile_photo_url' => $this->profilePhotoUrl($driver->user?->profile_photo),
            'rating' => round((float) ($driver->rating ?? 0), 2),
            'behavior_score' => $behaviorScore,
            'estimated_arrival_minutes' => max(1, (int) ceil(($distanceKm / 25) * 60)),
            'estimated_fare' => $fare,
            'distance_km' => $distanceKm,
            'accepting_requests' => $acceptingRequests,
            'availability_locked' => $hasActiveAssignment,
            'assignment_state' => $assignmentState,
            'fare_generated_at' => now()->toIso8601String(),
            'eta_generated_at' => now()->toIso8601String(),
            'online_status' => 'online',
            'current_location' => [
                'latitude' => $driver->getAttribute('matched_latitude') !== null ? (float) $driver->getAttribute('matched_latitude') : null,
                'longitude' => $driver->getAttribute('matched_longitude') !== null ? (float) $driver->getAttribute('matched_longitude') : null,
            ],
            'vehicle' => [
                'vehicle_type' => $vehicle?->vehicle_type,
                'plate_number' => $driver->license_plate,
                'color' => $vehicle?->color,
            ],
        ];

        if ($transportType === Ride::TRANSPORT_CAR) {
            $payload['available_seats'] = (int) ($vehicle?->seats ?? 0);
            $payload['comfort_tags'] = array_values(array_filter([
                $vehicle?->air_conditioning ? 'air_conditioning' : null,
                $vehicle && $vehicle->seats >= 6 ? 'extra_space' : null,
                $vehicle?->verified_at ? 'verified_vehicle' : null,
            ]));
        }

        return $payload;
    }

    private function behaviorScore(int $driverId): float
    {
        $behavior = DriverBehavior::query()
            ->where('driver_id', $driverId)
            ->latest('created_at')
            ->first();

        return round((float) ($behavior?->behavior_score ?? $behavior?->driving_score ?? 0.6), 4);
    }

    private function profilePhotoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset('storage/'.ltrim($path, '/'));
    }

    private function nextResponseVersion(): int
    {
        $version = Cache::increment('driver_matching_response_version');

        return is_int($version) ? $version : now()->getTimestamp();
    }

    private function distanceSql(): string
    {
        $clampStart = DB::connection()->getDriverName() === 'sqlite'
            ? 'MIN(1, MAX(-1,'
            : 'LEAST(1, GREATEST(-1,';
        $clampEnd = '))';

        return '(
            6371 * acos(
                '.$clampStart.'
                    cos(radians(?)) *
                    cos(radians(COALESCE(driver_locations.latitude, drivers.current_latitude))) *
                    cos(radians(COALESCE(driver_locations.longitude, drivers.current_longitude)) - radians(?)) +
                    sin(radians(?)) *
                    sin(radians(COALESCE(driver_locations.latitude, drivers.current_latitude)))
                '.$clampEnd.'
            )
        )';
    }

    private function haversineDistanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
