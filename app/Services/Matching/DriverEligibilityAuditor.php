<?php

namespace App\Services\Matching;

use App\Models\Driver;
use App\Models\Ride;
use App\Models\TripAssignmentAttempt;
use App\Services\TransportMappingService;
use Illuminate\Support\Facades\Schema;

class DriverEligibilityAuditor
{
    public const STRICT_HEARTBEAT_SECONDS = 30;
    public const LEGACY_HEARTBEAT_MINUTES = 15;

    public const PICKUP_LOCATIONS = [
        ['name' => 'Kigali CBD - City Tower', 'district' => 'Nyarugenge', 'lat' => -1.94407, 'lng' => 30.06188],
        ['name' => 'Nyabugogo Taxi Park', 'district' => 'Nyarugenge', 'lat' => -1.93918, 'lng' => 30.04454],
        ['name' => 'Remera Giporoso', 'district' => 'Gasabo', 'lat' => -1.95721, 'lng' => 30.10933],
        ['name' => 'Kimironko Market', 'district' => 'Gasabo', 'lat' => -1.94995, 'lng' => 30.11273],
        ['name' => 'Kacyiru Convention Area', 'district' => 'Gasabo', 'lat' => -1.95437, 'lng' => 30.09292],
        ['name' => 'Kimironko Bus Park', 'district' => 'Gasabo', 'lat' => -1.93698, 'lng' => 30.13014],
        ['name' => 'Kicukiro Centre', 'district' => 'Kicukiro', 'lat' => -1.97367, 'lng' => 30.09948],
        ['name' => 'Kigali International Airport', 'district' => 'Kicukiro', 'lat' => -1.96863, 'lng' => 30.13945],
        ['name' => 'Gisozi Memorial', 'district' => 'Gasabo', 'lat' => -1.92994, 'lng' => 30.06087],
        ['name' => 'Nyarutarama MTN Centre', 'district' => 'Gasabo', 'lat' => -1.93622, 'lng' => 30.09158],
        ['name' => 'Kimisagara Market', 'district' => 'Nyarugenge', 'lat' => -1.95672, 'lng' => 30.04342],
        ['name' => 'Nyamirambo Stadium', 'district' => 'Nyarugenge', 'lat' => -1.97879, 'lng' => 30.04025],
        ['name' => 'Kagugu Centre', 'district' => 'Gasabo', 'lat' => -1.90844, 'lng' => 30.09641],
        ['name' => 'Kibagabaga Hospital', 'district' => 'Gasabo', 'lat' => -1.92943, 'lng' => 30.12310],
        ['name' => 'Gikondo Expo Grounds', 'district' => 'Kicukiro', 'lat' => -1.97194, 'lng' => 30.06948],
        ['name' => 'Gatenga Centre', 'district' => 'Kicukiro', 'lat' => -1.99254, 'lng' => 30.10914],
        ['name' => 'Kabeza Centre', 'district' => 'Kicukiro', 'lat' => -1.97784, 'lng' => 30.12417],
        ['name' => 'Kacyiru Government Zone', 'district' => 'Gasabo', 'lat' => -1.94347, 'lng' => 30.08870],
        ['name' => 'University of Rwanda Gikondo', 'district' => 'Kicukiro', 'lat' => -1.96255, 'lng' => 30.07314],
        ['name' => 'Kigali Heights', 'district' => 'Gasabo', 'lat' => -1.95332, 'lng' => 30.09224],
    ];

    public const DRIVER_DISTRIBUTION = [
        ['zone' => 'Kigali CBD', 'count' => 3, 'lat' => -1.94407, 'lng' => 30.06188],
        ['zone' => 'Nyabugogo', 'count' => 3, 'lat' => -1.93918, 'lng' => 30.04454],
        ['zone' => 'Remera', 'count' => 3, 'lat' => -1.95721, 'lng' => 30.10933],
        ['zone' => 'Kimironko', 'count' => 3, 'lat' => -1.94995, 'lng' => 30.11273],
        ['zone' => 'Kacyiru', 'count' => 3, 'lat' => -1.95437, 'lng' => 30.09292],
        ['zone' => 'Kimironko Bus Park', 'count' => 3, 'lat' => -1.93698, 'lng' => 30.13014],
        ['zone' => 'Kicukiro', 'count' => 3, 'lat' => -1.97367, 'lng' => 30.09948],
        ['zone' => 'Kanombe', 'count' => 3, 'lat' => -1.96863, 'lng' => 30.13945],
        ['zone' => 'Gisozi', 'count' => 2, 'lat' => -1.92994, 'lng' => 30.06087],
        ['zone' => 'Nyarutarama', 'count' => 2, 'lat' => -1.93622, 'lng' => 30.09158],
        ['zone' => 'Kimisagara', 'count' => 2, 'lat' => -1.95672, 'lng' => 30.04342],
        ['zone' => 'Nyamirambo', 'count' => 2, 'lat' => -1.97879, 'lng' => 30.04025],
        ['zone' => 'Kagugu', 'count' => 2, 'lat' => -1.90844, 'lng' => 30.09641],
        ['zone' => 'Kibagabaga', 'count' => 3, 'lat' => -1.92943, 'lng' => 30.12310],
        ['zone' => 'Gikondo', 'count' => 2, 'lat' => -1.97194, 'lng' => 30.06948],
        ['zone' => 'Gatenga', 'count' => 2, 'lat' => -1.99254, 'lng' => 30.10914],
        ['zone' => 'Kabeza', 'count' => 2, 'lat' => -1.97784, 'lng' => 30.12417],
        ['zone' => 'Kacyiru Government Zone', 'count' => 2, 'lat' => -1.94347, 'lng' => 30.08870],
        ['zone' => 'University Areas', 'count' => 2, 'lat' => -1.96255, 'lng' => 30.07314],
    ];

    /**
     * @return array{eligible: bool, reasons: list<string>, warnings: list<string>, score: float, distance_km: float|null}
     */
    public function evaluate(Driver $driver, string $transportType = 'private_car', ?float $pickupLat = null, ?float $pickupLng = null, float $radiusKm = 5.0, bool $strictV3 = true): array
    {
        $reasons = [];
        $warnings = [];
        $normalizedTransport = $this->normalizeTransportType($transportType);
        $vehicleTypes = TransportMappingService::getVehicleTypesFor($normalizedTransport);

        if ((string) $driver->status !== 'approved') {
            $reasons[] = 'driver status is not approved';
        }

        if (Schema::hasColumn('drivers', 'is_active') && ! (bool) $driver->getAttribute('is_active')) {
            $reasons[] = 'driver is not active';
        } elseif (! Schema::hasColumn('drivers', 'is_active')) {
            $warnings[] = 'drivers.is_active column is missing but V3 strict matching queries it';
        }

        if (! (bool) ($driver->user?->is_approved)) {
            $reasons[] = 'user account is not approved';
        }

        if (! (bool) ($driver->user?->is_verified)) {
            $warnings[] = 'user account is not verified';
        }

        if (! (bool) $driver->is_online) {
            $reasons[] = 'driver.is_online is false';
        }

        if (! in_array((string) $driver->availability_status, ['online', 'available'], true)) {
            $reasons[] = 'availability_status is not online/available';
        }

        if ($driver->is_available !== null && ! (bool) $driver->is_available) {
            $reasons[] = 'is_available flag is false';
        }

        if ($driver->current_trip_id) {
            $reasons[] = 'current_trip_id is set';
        }

        if (! $driver->last_seen_at) {
            $reasons[] = 'missing heartbeat last_seen_at';
        } elseif ($strictV3 && $driver->last_seen_at->lt(now()->subSeconds(self::STRICT_HEARTBEAT_SECONDS))) {
            $reasons[] = 'heartbeat older than V3 30 seconds';
        } elseif ($driver->last_seen_at->lt(now()->subMinutes(self::LEGACY_HEARTBEAT_MINUTES))) {
            $reasons[] = 'heartbeat older than legacy 15 minutes';
        }

        $location = $driver->relationLoaded('user')
            ? $this->latestLocation($driver)
            : null;
        $lat = $location['lat'] ?? $driver->current_latitude ?? $driver->last_location_lat;
        $lng = $location['lng'] ?? $driver->current_longitude ?? $driver->last_location_lng;

        if ($lat === null || $lng === null) {
            $reasons[] = 'missing current location';
        }

        if (isset($location['is_online']) && ! (bool) $location['is_online']) {
            $warnings[] = 'driver_locations row is offline';
        }

        if (isset($location['last_activity_at']) && $location['last_activity_at'] && $location['last_activity_at']->lt(now()->subMinutes(15))) {
            $warnings[] = 'driver_locations heartbeat is older than 15 minutes';
        }

        $vehicles = $driver->relationLoaded('vehicles') ? $driver->vehicles : $driver->vehicles()->get();
        $activeCompatibleVehicle = $vehicles->first(fn ($vehicle): bool => (bool) $vehicle->is_active && in_array((string) $vehicle->vehicle_type, $vehicleTypes, true));
        if (! $activeCompatibleVehicle) {
            $reasons[] = 'no active compatible vehicle for '.$normalizedTransport;
        } elseif (! $activeCompatibleVehicle->verified_at) {
            $warnings[] = 'compatible vehicle is active but not verified';
        }

        if ($driver->trips()->whereIn('status', ['PENDING', 'ACCEPTED', 'STARTED'])->exists()) {
            $reasons[] = 'active trip exists';
        }

        if ($driver->motorcycleTrips()->whereIn('status', ['ASSIGNED', 'DRIVER_ASSIGNED', 'PASSENGER_WAITING', 'IN_PROGRESS'])->exists()) {
            $reasons[] = 'active motorcycle trip exists';
        }

        if ($driver->assignmentAttempts()
            ->whereIn('status', [TripAssignmentAttempt::STATUS_PENDING, TripAssignmentAttempt::STATUS_NOTIFIED])
            ->where('expires_at', '>', now())
            ->exists()) {
            $reasons[] = 'pending assignment attempt lock exists';
        }

        $distanceKm = null;
        if ($pickupLat !== null && $pickupLng !== null && $lat !== null && $lng !== null) {
            $distanceKm = $this->distanceKm($pickupLat, $pickupLng, (float) $lat, (float) $lng);
            if ($distanceKm > $radiusKm) {
                $reasons[] = 'outside '.$radiusKm.' km search radius';
            }
        }

        $rating = (float) ($driver->rating ?? 4.5);
        $distanceScore = $distanceKm === null ? 0.5 : 1 / (1 + $distanceKm);
        $ratingScore = min($rating / 5, 1.0);
        $score = round((($distanceScore * 0.8) + ($ratingScore * 0.2)) * 100, 2);

        return [
            'eligible' => $reasons === [],
            'reasons' => $reasons,
            'warnings' => $warnings,
            'score' => $score,
            'distance_km' => $distanceKm !== null ? round($distanceKm, 3) : null,
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

    public function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    /**
     * @return array{lat: float|null, lng: float|null, is_online: bool|null, last_activity_at: \Carbon\CarbonInterface|null}|null
     */
    private function latestLocation(Driver $driver): ?array
    {
        $ids = array_values(array_filter(array_unique([
            (int) $driver->id,
            (int) $driver->user_id,
            (int) ($driver->user?->mobile_user_id ?? 0),
        ])));

        $location = \App\Models\DriverLocation::query()
            ->whereIn('driver_id', $ids)
            ->orderByDesc('id')
            ->first();

        if (! $location) {
            return null;
        }

        return [
            'lat' => $location->latitude ?? $location->lat,
            'lng' => $location->longitude ?? $location->lng,
            'is_online' => $location->is_online,
            'last_activity_at' => $location->last_activity_at,
        ];
    }
}
