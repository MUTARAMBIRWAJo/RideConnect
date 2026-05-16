<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\DriverBehavior;
use App\Models\PassengerBehavior;
use App\Models\Trip;

/**
 * MatchingEngine calculates a compatibility score using live driver, passenger,
 * route, and weather snapshots. Null values are treated as unknown rather than
 * being replaced with fabricated data.
 */
class MatchingEngine
{
    private const WEIGHTS = [
        'distance' => 0.25,
        'driver_rating' => 0.15,
        'driver_behavior' => 0.20,
        'passenger_reliability' => 0.15,
        'route_conditions' => 0.15,
        'weather_conditions' => 0.10,
    ];

    public function calculateMatchingScore(Trip $trip, array $driverIds = []): array
    {
        $scores = [];

        foreach ($driverIds as $driverId) {
            $score = $this->computeScore($trip, $driverId);
            $scores[$driverId] = $score;
        }

        arsort($scores);

        return $scores;
    }

    private function computeScore(Trip $trip, int $driverId): float
    {
        $distanceScore = $this->getDistancePenalty($trip);
        $driverRatingScore = $this->getDriverRatingScore($driverId);
        $behaviorScore = $this->getDriverBehaviorScore($driverId);
        $passengerReliabilityScore = $this->getPassengerReliabilityScore($trip);
        $routeConditionScore = $this->getRouteConditionScore($trip);
        $weatherConditionScore = $this->getWeatherConditionScore($trip);

        $compositeScore = (
            self::WEIGHTS['distance'] * $distanceScore +
            self::WEIGHTS['driver_rating'] * $driverRatingScore +
            self::WEIGHTS['driver_behavior'] * $behaviorScore +
            self::WEIGHTS['passenger_reliability'] * $passengerReliabilityScore +
            self::WEIGHTS['route_conditions'] * $routeConditionScore +
            self::WEIGHTS['weather_conditions'] * $weatherConditionScore
        );

        return round(max(0.0, min(1.0, $compositeScore)), 4);
    }

    private function getDistancePenalty(Trip $trip): float
    {
        if (! $trip->routeState) {
            return 0.5;
        }

        $distanceKm = (float) $trip->routeState->distance_km;
        $maxPenaltyDistance = 50.0;
        $normalizedDistance = min(1.0, $distanceKm / $maxPenaltyDistance);

        return 1.0 - $normalizedDistance;
    }

    private function getDriverRatingScore(int $driverId): float
    {
        $driver = Driver::query()->find($driverId);

        if (! $driver) {
            return 0.5;
        }

        $rating = (float) ($driver->rating ?? 3.0);

        return min(1.0, $rating / 5.0);
    }

    private function getDriverBehaviorScore(int $driverId): float
    {
        $behavior = DriverBehavior::query()->where('driver_id', $driverId)->latest('created_at')->first();

        if (! $behavior) {
            return 0.6;
        }

        return (float) ($behavior->driving_score ?? $behavior->behavior_score ?? 0.6);
    }

    private function getPassengerReliabilityScore(Trip $trip): float
    {
        $mobileUser = $trip->passenger;
        $userId = $mobileUser?->user?->id;

        if (! $userId) {
            return 0.5;
        }

        $behavior = PassengerBehavior::query()->where('user_id', $userId)->latest('created_at')->first();

        if (! $behavior) {
            return 0.5;
        }

        return (float) ($behavior->reliability_score ?? $behavior->payment_reliability ?? 0.5);
    }

    private function getRouteConditionScore(Trip $trip): float
    {
        $routeState = $trip->routeState;

        if (! $routeState) {
            return 0.5;
        }

        $trafficLevel = $routeState->traffic_level;
        $incidentFlag = $routeState->incident_flag;
        $averageSpeed = $routeState->average_speed;

        $factors = [];

        if ($trafficLevel !== null) {
            $factors[] = 1 - (min(5, max(1, (int) $trafficLevel)) / 5);
        }

        if ($incidentFlag !== null) {
            $factors[] = $incidentFlag ? 0.2 : 1.0;
        }

        if ($averageSpeed !== null) {
            $factors[] = min(1.0, max(0.0, ((float) $averageSpeed) / 60.0));
        }

        return $this->meanOrNeutral($factors);
    }

    private function getWeatherConditionScore(Trip $trip): float
    {
        $weatherCondition = $trip->weatherCondition;

        if (! $weatherCondition) {
            return 0.5;
        }

        $factors = [];

        if ($weatherCondition->weather_factor !== null) {
            $factors[] = (float) $weatherCondition->weather_factor;
        }

        if ($weatherCondition->rain_intensity !== null) {
            $factors[] = 1 - min(1.0, max(0.0, ((float) $weatherCondition->rain_intensity) / 100.0));
        }

        if ($weatherCondition->visibility !== null) {
            $factors[] = min(1.0, max(0.0, ((float) $weatherCondition->visibility) / 10.0));
        }

        if ($weatherCondition->wind_speed !== null) {
            $factors[] = 1 - min(1.0, max(0.0, ((float) $weatherCondition->wind_speed) / 100.0));
        }

        return $this->meanOrNeutral($factors);
    }

    private function meanOrNeutral(array $values): float
    {
        $filtered = array_values(array_filter($values, static fn ($value) => $value !== null));

        if ($filtered === []) {
            return 0.5;
        }

        return round(array_sum($filtered) / count($filtered), 4);
    }
}
