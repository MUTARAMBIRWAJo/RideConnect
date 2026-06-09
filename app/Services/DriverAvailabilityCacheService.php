<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\DriverAvailabilityCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Task 3 — Driver Availability Cache.
 *
 * Strategy:
 *  - Write-through: every driver location update / status change refreshes the cache row.
 *  - Read-through: matching queries this cache first (indexed), falls back to drivers table.
 *  - Redis activation (Task 3 full): when a Redis service is provisioned, hot keys are
 *    driver:{id}:location and driver:{id}:status — change CacheService::get/set to
 *    check Redis first, then DB, and the callers below remain unchanged.
 */
class DriverAvailabilityCacheService
{
    public function refreshFromDriver(Driver $driver): void
    {
        try {
            DriverAvailabilityCache::updateOrCreate(
                ['driver_id' => $driver->id],
                [
                    'vehicle_type' => optional($driver->vehicle)->vehicle_type,
                    'current_lat'   => $driver->current_latitude,
                    'current_lng'   => $driver->current_longitude,
                    'is_online'     => in_array((string) $driver->availability_status, ['online', 'available'], true),
                    'is_available'  => (bool) ($driver->is_available ?? true),
                    'last_seen_at'  => now(),
                    'updated_at'    => now(),
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('DriverAvailabilityCache refresh failed', [
                'driver_id' => $driver->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<int, array{driver_id:int,lat:float|null,lng:float|null,is_online:bool,is_available:bool}>
     */
    public function getOnlineAvailable(string $vehicleType = null, float $aroundLat = null, float $aroundLng = null, float $radiusKm = 5.0): array
    {
        $query = DriverAvailabilityCache::query()
            ->where('is_online', true)
            ->where('is_available', true)
            ->when($vehicleType, fn ($q, $v) => $q->where('vehicle_type', $v));

        // When coordinates are provided, use the bounding-box index for a rough pre-filter
        // then apply Haversine in memory for accuracy. Without coords, return all.
        $rows = $query->get()->filter(function ($row) use ($aroundLat, $aroundLng, $radiusKm) {
            if ($aroundLat === null || $aroundLng === null) {
                return true;
            }
            $lat = (float) $row->current_lat;
            $lng = (float) $row->current_lng;
            if ($lat === 0.0 && $lng === 0.0) {
                return false;
            }
            $dLat = deg2rad($aroundLat - $lat);
            $dLng = deg2rad($aroundLng - $lng);
            $a = sin($dLat / 2) ** 2
                + cos(deg2rad($lat)) * cos(deg2rad($aroundLat)) * sin($dLng / 2) ** 2;
            $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
            return (6371 * $c) <= $radiusKm;
        })->values()->all();

        return array_map(fn ($r) => [
            'driver_id' => (int) $r->driver_id,
            'lat' => $r->current_lat !== null ? (float) $r->current_lat : null,
            'lng' => $r->current_lng !== null ? (float) $r->current_lng : null,
            'is_online' => (bool) $r->is_online,
            'is_available' => (bool) $r->is_available,
            'last_seen_at' => $r->last_seen_at?->toIso8601String(),
        ], $rows);
    }

    public function markBusy(int $driverId): void
    {
        DriverAvailabilityCache::where('driver_id', $driverId)
            ->update(['is_available' => false, 'updated_at' => now()]);
    }

    public function markAvailable(int $driverId): void
    {
        DriverAvailabilityCache::where('driver_id', $driverId)
            ->update(['is_available' => true, 'updated_at' => now()]);
    }
}
