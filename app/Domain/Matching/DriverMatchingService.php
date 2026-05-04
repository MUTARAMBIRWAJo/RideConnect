<?php

namespace App\Domain\Matching;

use App\Models\Ride;
use App\Models\Trip;
use App\Services\Realtime\RealtimeGateway;
use App\Services\TransportMappingService;
use Illuminate\Support\Collection;

class DriverMatchingService implements DriverMatchingStrategy
{
    public function __construct(private readonly RealtimeGateway $realtimeGateway)
    {
    }

    public function findBestDriver(Ride $ride, Collection $drivers, ?Trip $trip = null): ?array
    {
        $eligible = $drivers
            ->filter(function ($driver) use ($ride) {
                $vehicle = $driver->vehicles->first() ?? $driver->vehicles()->first();

                if (!$vehicle) {
                    return false;
                }

                $isAvailable = ($driver->availability_status ?? 'offline') === 'online';

                return $isAvailable
                    && TransportMappingService::isCompatible((string) $vehicle->vehicle_type, (string) $ride->transport_type);
            })
            ->values();

        if ($eligible->isEmpty()) {
            return null;
        }

        $scored = $eligible->map(function ($driver, int $index) use ($ride) {
            $distanceKm = $this->distanceToRide(
                $ride,
                (float) ($driver->current_latitude ?? 0),
                (float) ($driver->current_longitude ?? 0)
            );

            return [
                'driver' => $driver,
                // Placeholder scores for future ML integration.
                'driver_score' => 0.0,
                'passenger_behavior_score' => 0.0,
                'route_efficiency_score' => 0.0,
                // Deterministic fallback ranking.
                'distance_km' => $distanceKm,
                'availability_rank' => (($driver->availability_status ?? 'offline') === 'online') ? 0 : 1,
                'tie_breaker' => (int) $driver->id,
            ];
        })->all();

        $scored = $this->applyMlScore($scored);

        usort($scored, function (array $a, array $b): int {
            if ($a['distance_km'] !== $b['distance_km']) {
                return $a['distance_km'] <=> $b['distance_km'];
            }

            if ($a['availability_rank'] !== $b['availability_rank']) {
                return $a['availability_rank'] <=> $b['availability_rank'];
            }

            return $a['tie_breaker'] <=> $b['tie_breaker'];
        });

        $best = $scored[0] ?? null;

        if (!$best) {
            return null;
        }

        if ($trip !== null) {
            $this->realtimeGateway->broadcast(
                "driver:{$best['driver']->id}",
                'trip.request',
                [
                    'trip_id' => $trip->id,
                    'pickup' => $trip->pickup_location ?? '',
                ]
            );
        }

        return [
            'driver' => $best['driver'],
            'driver_score' => $best['driver_score'],
            'passenger_behavior_score' => $best['passenger_behavior_score'],
            'route_efficiency_score' => $best['route_efficiency_score'],
            'distance_km' => $best['distance_km'],
        ];
    }

    /**
     * ML extension hook (placeholder only).
     * Current implementation returns raw, non-ML scores unchanged.
     */
    public function applyMlScore(array $drivers): array
    {
        return $drivers;
    }

    private function distanceToRide(Ride $ride, float $driverLat, float $driverLng): float
    {
        $originLat = (float) ($ride->origin_lat ?? 0);
        $originLng = (float) ($ride->origin_lng ?? 0);

        if ($driverLat === 0.0 && $driverLng === 0.0) {
            return INF;
        }

        $earthRadiusKm = 6371;
        $latFrom = deg2rad($driverLat);
        $latTo = deg2rad($originLat);
        $latDelta = deg2rad($originLat - $driverLat);
        $lngDelta = deg2rad($originLng - $driverLng);

        $a = sin($latDelta / 2) ** 2 + cos($latFrom) * cos($latTo) * sin($lngDelta / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadiusKm * $c, 3);
    }
}
