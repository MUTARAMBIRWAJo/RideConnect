<?php

namespace App\Services;

use App\Models\Trip;
use Illuminate\Support\Collection;

class DemandPredictionService
{
    /**
     * Build demand points using recent trip pickup density as a lightweight AI proxy.
     */
    public function predict(): Collection
    {
        $recentTrips = Trip::query()
            ->whereIn('status', ['PENDING', 'ACCEPTED', 'STARTED', 'COMPLETED'])
            ->whereNotNull('pickup_lat')
            ->whereNotNull('pickup_lng')
            ->where('created_at', '>=', now()->subHours(6))
            ->get(['pickup_lat', 'pickup_lng']);

        if ($recentTrips->isEmpty()) {
            return collect([
                ['lat' => -1.9440, 'lng' => 30.0610, 'intensity' => 0.45],
                ['lat' => -1.9500, 'lng' => 30.0580, 'intensity' => 0.70],
                ['lat' => -1.9380, 'lng' => 30.0720, 'intensity' => 0.90],
            ]);
        }

        $buckets = $recentTrips
            ->groupBy(function (Trip $trip) {
                return round((float) $trip->pickup_lat, 3).':'.round((float) $trip->pickup_lng, 3);
            })
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'lat' => round((float) $first->pickup_lat, 3),
                    'lng' => round((float) $first->pickup_lng, 3),
                    'count' => $group->count(),
                ];
            })
            ->values();

        $maxCount = (int) max(1, (int) $buckets->max('count'));

        return $buckets
            ->map(function (array $bucket) use ($maxCount) {
                $normalized = $bucket['count'] / $maxCount;

                return [
                    'lat' => $bucket['lat'],
                    'lng' => $bucket['lng'],
                    'intensity' => round(min(1, max(0.2, $normalized)), 3),
                ];
            })
            ->values();
    }
}
