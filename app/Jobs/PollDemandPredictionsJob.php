<?php

namespace App\Jobs;

use App\Models\Driver;
use App\Models\MotorcycleTrip;
use App\Models\Trip;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PollDemandPredictionsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Log::info('PollDemandPredictionsJob: Starting real-time demand calculation.');

        // 1. Fetch recent trip requests (last 10 minutes)
        $tenMinutesAgo = now()->subMinutes(10);

        // Fetch pending/active trips to identify demand
        $motorTrips = MotorcycleTrip::where('created_at', '>=', $tenMinutesAgo)
            ->whereIn('status', ['REQUESTED', 'MATCHING', 'MATCHING_PENDING'])
            ->get(['id', 'pickup_lat', 'pickup_lng']);

        $standardTrips = Trip::where('created_at', '>=', $tenMinutesAgo)
            ->whereIn('status', ['REQUESTED', 'MATCHING'])
            ->get(['id', 'pickup_lat', 'pickup_lng']);

        $allRequests = $motorTrips->concat($standardTrips);

        if ($allRequests->isEmpty()) {
            Log::info('PollDemandPredictionsJob: No recent trip requests found.');
            return;
        }

        // 2. Compute demand density per geo-grid (simple rounding to ~1-2km resolution)
        // Lat/Lng precision of 2 decimal places is approx 1.1km.
        $zones = [];
        foreach ($allRequests as $req) {
            if (!$req->pickup_lat || !$req->pickup_lng) continue;

            $gridLat = round($req->pickup_lat, 2);
            $gridLng = round($req->pickup_lng, 2);
            $zoneId = "zone_{$gridLat}_{$gridLng}";

            if (!isset($zones[$zoneId])) {
                $zones[$zoneId] = [
                    'id' => $zoneId,
                    'lat' => $gridLat,
                    'lng' => $gridLng,
                    'demand_count' => 0,
                ];
            }
            $zones[$zoneId]['demand_count']++;
        }

        // Filter for high-demand zones (e.g., at least 2 requests in the same grid)
        $highDemandZones = array_filter($zones, fn($z) => $z['demand_count'] >= 1);

        if (empty($highDemandZones)) {
            Log::info('PollDemandPredictionsJob: No high-demand hotspots identified.');
            return;
        }

        // 3. Find available drivers and notify
        foreach ($highDemandZones as $zone) {
            $this->processZoneDemand($zone);
        }
    }

    private function processZoneDemand(array $zone): void
    {
        $zoneId = $zone['id'];
        $demandCount = $zone['demand_count'];
        $radiusKm = 5.0; // 5 km radius

        // Find available drivers within radius
        // We use a simplified Haversine approximation in the DB or collect all and filter.
        // For performance, we collect ONLINE drivers without an active trip and calculate distance.
        $availableDrivers = Driver::with('user')
            ->where('status', 'ONLINE')
            ->where('is_available', true)
            ->whereNull('current_trip_id')
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->get()
            ->filter(function ($driver) use ($zone, $radiusKm) {
                // Ensure driver has no active moto trips either
                if ($driver->hasActiveMotoTrip()) return false;

                $dist = $this->haversineKm($driver->current_latitude, $driver->current_longitude, $zone['lat'], $zone['lng']);
                $driver->distance_to_zone = $dist;
                return $dist <= $radiusKm;
            });

        $availableCount = $availableDrivers->count();

        if ($availableCount === 0) {
            Log::info("PollDemandPredictionsJob: High demand in {$zoneId} ({$demandCount} reqs), but no available drivers nearby.");
            return;
        }

        // 4. Calculate limit: NEVER notify more drivers than demand
        $limit = min($demandCount, $availableCount);

        // 5. Sorting priority: Closest distance, then rating (desc)
        $selectedDrivers = $availableDrivers->sortBy([
            fn($a, $b) => $a->distance_to_zone <=> $b->distance_to_zone,
            fn($a, $b) => $b->rating <=> $a->rating,
        ])->take($limit);

        Log::info("PollDemandPredictionsJob: Zone {$zoneId} has demand {$demandCount}, available {$availableCount}. Notifying {$limit} drivers.");

        // 6. Notify selected drivers with caching & throttling
        $notifiedCount = 0;
        foreach ($selectedDrivers as $driver) {
            $cacheKey = "demand_push_alert_{$driver->id}_{$zoneId}";

            // Throttle: Do not re-alert same driver within 10 minutes for same zone
            if (Cache::has($cacheKey)) {
                continue;
            }

            Cache::put($cacheKey, true, now()->addMinutes(10));

            // Log to DB
            $payload = [
                'type' => 'demand_opportunity',
                'zone' => $zoneId,
                'estimated_requests' => $demandCount,
                'message' => 'High demand detected near your location. Accept trips now to earn more.',
                'lat' => $zone['lat'],
                'lng' => $zone['lng']
            ];

            DB::table('demand_push_logs')->insert([
                'zone_id' => $zoneId,
                'driver_id' => $driver->id,
                'demand_count' => $demandCount,
                'available_drivers_count' => $availableCount,
                'lat' => $driver->current_latitude,
                'lng' => $driver->current_longitude,
                'payload' => json_encode($payload),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Dispatch notification
            if ($driver->user) {
                // Using generic notification array for simplicity or custom notification
                $driver->user->notify(new \App\Notifications\HighDemandZoneNotification($zoneId, 1.0, $payload));
            }

            $notifiedCount++;
        }

        Log::info("PollDemandPredictionsJob: Successfully notified {$notifiedCount} drivers for zone {$zoneId}.");
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // km
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);
        
        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lngDelta / 2) * sin($lngDelta / 2);
             
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}
