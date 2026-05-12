<?php

namespace App\Services;

use App\Models\DriverBehavior;
use App\Models\Booking;
use App\Models\MobileUser;
use App\Models\PassengerBehavior;
use App\Models\RouteState;
use App\Models\Review;
use App\Models\Trip;
use App\Models\WeatherCondition;
use App\Models\TransportRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TripConditionService
{
    public function captureSnapshot(Trip $trip): Trip
    {
        if (!Schema::hasTable('trips')) {
            return $trip;
        }

        $routeState = $this->createRouteState($trip);
        $weatherCondition = $this->createWeatherCondition($trip);
        $driverBehavior = $this->createDriverBehavior($trip);
        $passengerBehavior = $this->createPassengerBehavior($trip);
        $tripQualityScore = $this->calculateTripQualityScore($driverBehavior, $passengerBehavior, $routeState, $weatherCondition);
        $etaDeviationMinutes = $this->calculateEtaDeviationMinutes($trip, $routeState);

        $trip->forceFill([
            'route_state_id' => $routeState?->id,
            'weather_condition_id' => $weatherCondition?->id,
            'driver_behavior_id' => $driverBehavior?->id,
            'passenger_behavior_id' => $passengerBehavior?->id,
            'trip_quality_score' => $tripQualityScore,
            'eta_deviation_minutes' => $etaDeviationMinutes,
        ]);

        $trip->save();

        return $trip->fresh();
    }

    public function getCurrentRouteState(float $pickupLat, float $pickupLng, float $dropoffLat, float $dropoffLng, ?int $routeId = null): array
    {
        $trafficState = $this->calculateTrafficState($routeId, $pickupLat, $pickupLng, $dropoffLat, $dropoffLng);

        $distance = $this->haversineDistance($pickupLat, $pickupLng, $dropoffLat, $dropoffLng);
        $estimatedDuration = $trafficState['estimated_duration_min'] ?? null;

        return [
            'route_id' => $routeId,
            'pickup_lat' => $pickupLat,
            'pickup_lng' => $pickupLng,
            'dropoff_lat' => $dropoffLat,
            'dropoff_lng' => $dropoffLng,
            'distance_km' => round($distance, 3),
            'estimated_duration_min' => $estimatedDuration,
            'traffic_level' => $trafficState['traffic_level'],
            'road_condition' => $trafficState['road_condition'],
            'average_speed' => $trafficState['average_speed'],
            'incident_flag' => $trafficState['incident_flag'],
            'congestion_index' => $trafficState['congestion_index'],
            'route_name' => $trafficState['route_name'],
            'route_geometry' => null,
        ];
    }

    public function getCurrentWeatherState(float $lat, float $lng): array
    {
        return [
            'location_lat' => $lat,
            'location_lng' => $lng,
            'weather_type' => null,
            'temperature' => null,
            'rain_intensity' => null,
            'visibility' => null,
            'wind_speed' => null,
            'condition' => null,
            'temperature_celsius' => null,
            'wind_speed_kmh' => null,
            'precipitation_mm' => null,
            'weather_factor' => 1.0,
            'description' => null,
            'recorded_at' => now(),
        ];
    }

    private function createRouteState(Trip $trip): ?RouteState
    {
        if (!Schema::hasTable('route_states')) {
            return null;
        }

        $route = $trip->ride?->route;
        $routeId = $route?->id ?? $trip->ride?->route_id;
        $pickupLat = $trip->pickup_lat;
        $pickupLng = $trip->pickup_lng;
        $dropoffLat = $trip->dropoff_lat;
        $dropoffLng = $trip->dropoff_lng;
        $distance = $this->haversineDistance($pickupLat, $pickupLng, $dropoffLat, $dropoffLng);
        $trafficState = $this->calculateTrafficState($routeId, $pickupLat, $pickupLng, $dropoffLat, $dropoffLng);

        return RouteState::create([
            'trip_id' => $trip->id,
            'route_id' => $routeId,
            'pickup_lat' => $pickupLat,
            'pickup_lng' => $pickupLng,
            'dropoff_lat' => $dropoffLat,
            'dropoff_lng' => $dropoffLng,
            'route_name' => $route?->name ?? $route?->route_code ?? $trip->ride?->transport_type,
            'distance_km' => round($distance, 3),
            'estimated_duration_min' => $trafficState['estimated_duration_min'],
            'traffic_level' => $trafficState['traffic_level'],
            'road_condition' => $trafficState['road_condition'],
            'average_speed' => $trafficState['average_speed'],
            'incident_flag' => $trafficState['incident_flag'],
            'congestion_index' => $trafficState['congestion_index'],
            'route_geometry' => null,
        ]);
    }

    private function createWeatherCondition(Trip $trip): ?WeatherCondition
    {
        if (!Schema::hasTable('weather_conditions')) {
            return null;
        }

        $weatherState = $this->getCurrentWeatherState($trip->pickup_lat, $trip->pickup_lng);

        return WeatherCondition::create(array_merge($weatherState, [
            'trip_id' => $trip->id,
        ]));
    }

    private function createDriverBehavior(Trip $trip): ?DriverBehavior
    {
        if (!$trip->driver_id || !Schema::hasTable('driver_behaviors')) {
            return null;
        }

        $driver = $trip->driver;
        $reviews = Schema::hasTable('reviews')
            ? Review::query()->where('driver_id', $trip->driver_id)
            : null;
        $rating = $reviews?->avg('rating') ?? $driver?->rating;

        $totalTrips = Trip::query()->where('driver_id', $trip->driver_id)->count();
        $acceptedTrips = Trip::query()->where('driver_id', $trip->driver_id)->whereNotNull('accepted_at')->count();
        $cancelledTrips = Trip::query()->where('driver_id', $trip->driver_id)
            ->where(function ($query) {
                $query->where('status', 'CANCELLED')->orWhereNotNull('rejected_at');
            })
            ->count();
        $completedTrips = Trip::query()->where('driver_id', $trip->driver_id)->whereNotNull('completed_at')->count();
        $onTimeTrips = Trip::query()->where('driver_id', $trip->driver_id)
            ->whereNotNull('accepted_at')
            ->whereNotNull('started_at')
            ->get()
            ->filter(function (Trip $driverTrip): bool {
                return $driverTrip->accepted_at && $driverTrip->started_at
                    && $driverTrip->started_at->lessThanOrEqualTo($driverTrip->accepted_at->copy()->addMinutes(15));
            })
            ->count();

        $acceptanceRate = $totalTrips > 0 ? round($acceptedTrips / $totalTrips, 4) : 0.0;
        $cancellationRate = $totalTrips > 0 ? round($cancelledTrips / $totalTrips, 4) : 0.0;
        $onTimeRate = $completedTrips > 0 ? round($onTimeTrips / max(1, $completedTrips), 4) : 0.0;

        $driverScoreComponents = [];
        if ($rating !== null) {
            $driverScoreComponents[] = $rating / 5;
        }
        if ($totalTrips > 0) {
            $driverScoreComponents[] = $acceptanceRate;
            $driverScoreComponents[] = $onTimeRate;
            $driverScoreComponents[] = 1 - $cancellationRate;
        }

        $drivingScore = $this->compositeScore($driverScoreComponents) ?? 0.5;

        return DriverBehavior::create([
            'driver_id' => $trip->driver_id,
            'trip_id' => $trip->id,
            'rating' => $rating ?? 0.0,
            'acceptance_rate' => $acceptanceRate,
            'cancellation_rate' => $cancellationRate,
            'on_time_rate' => $onTimeRate,
            'driving_score' => $drivingScore,
            'behavior_score' => $drivingScore,
            'notes' => 'Snapshot captured from live trip history',
            'reviewed_at' => now(),
        ]);
    }

    private function createPassengerBehavior(Trip $trip): ?PassengerBehavior
    {
        if (!$trip->passenger_id || !Schema::hasTable('passenger_behaviors')) {
            return null;
        }

        $mobileUser = MobileUser::query()->find($trip->passenger_id);
        $user = $mobileUser?->user;
        $totalBookings = $user && Schema::hasTable('bookings') ? Booking::query()->where('user_id', $user->id)->count() : 0;
        $cancelledBookings = $user && Schema::hasTable('bookings') ? Booking::query()->where('user_id', $user->id)->where('status', 'CANCELLED')->count() : 0;
        $noShowBookings = $user && Schema::hasTable('bookings') ? Booking::query()->where('user_id', $user->id)->where('status', 'NO_SHOW')->count() : 0;
        $paidBookings = 0;
        if ($user && Schema::hasTable('payments')) {
            $paidBookings = Booking::query()->where('user_id', $user->id)->whereHas('payment', function ($query) {
                $query->whereNotNull('paid_at')->whereNull('refunded_at');
            })->count();
        }

        $reviews = $user && Schema::hasTable('reviews') ? Review::query()->where('user_id', $user->id) : null;
        $rating = $reviews?->avg('rating');

        $cancellationRate = $totalBookings > 0 ? round($cancelledBookings / $totalBookings, 4) : 0.0;
        $noShowRate = $totalBookings > 0 ? round($noShowBookings / $totalBookings, 4) : 0.0;
        $paymentReliability = $totalBookings > 0 ? round($paidBookings / $totalBookings, 4) : 0.5;

        $passengerScoreComponents = [];
        if ($rating !== null) {
            $passengerScoreComponents[] = $rating / 5;
        }
        if ($totalBookings > 0) {
            $passengerScoreComponents[] = $paymentReliability;
            $passengerScoreComponents[] = 1 - $cancellationRate;
            $passengerScoreComponents[] = 1 - $noShowRate;
        }

        $reliabilityScore = $this->compositeScore($passengerScoreComponents) ?? 0.5;

        return PassengerBehavior::create([
            'user_id' => $user?->id,
            'passenger_id' => $trip->passenger_id,
            'trip_id' => $trip->id,
            'rating' => $rating ?? 0.0,
            'reliability_score' => $reliabilityScore,
            'cancellation_rate' => $cancellationRate,
            'no_show_rate' => $noShowRate,
            'payment_reliability' => $paymentReliability,
            'total_trips' => $totalBookings,
            'notes' => 'Passenger reliability snapshot captured from live booking and payment history',
        ]);
    }

    private function calculateTrafficState(?int $routeId, float $pickupLat, float $pickupLng, float $dropoffLat, float $dropoffLng): array
    {
        $historicalTrips = Trip::query()
            ->when($routeId, fn ($query) => $query->whereHas('ride', fn ($rideQuery) => $rideQuery->where('route_id', $routeId)))
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->get();

        $historicalCount = $historicalTrips->count();
        $averageDuration = $historicalTrips->avg(function (Trip $trip) {
            return $trip->started_at?->diffInMinutes($trip->completed_at) ?? null;
        });

        $trafficLevel = $historicalCount > 0 ? (int) max(1, min(5, round($historicalCount / 5))) : 3;
        $averageSpeed = $this->calculateAverageSpeed($historicalTrips);
        $incidentFlag = $historicalCount > 0 ? ((int) (Trip::query()
                ->when($routeId, fn ($query) => $query->whereHas('ride', fn ($rideQuery) => $rideQuery->where('route_id', $routeId)))
                ->where(function ($query) {
                    $query->where('status', 'CANCELLED')->orWhereNotNull('rejected_at');
                })
                ->count()) > max(1, (int) round($historicalCount * 0.25))) : null;

        return [
            'traffic_level' => $trafficLevel,
            'road_condition' => $this->describeRoadCondition($trafficLevel),
            'average_speed' => $averageSpeed,
            'incident_flag' => $incidentFlag,
            'congestion_index' => round($trafficLevel / 5, 4),
            'estimated_duration_min' => $averageDuration !== null ? (int) round($averageDuration) : null,
            'route_name' => $routeId && Schema::hasTable('routes') ? (TransportRoute::query()->find($routeId)?->name ?? null) : null,
        ];
    }

    private function calculateAverageSpeed($historicalTrips): ?float
    {
        $durations = $historicalTrips
            ->map(function (Trip $trip) {
                if (!$trip->actual_distance || !$trip->started_at || !$trip->completed_at) {
                    return null;
                }

                $minutes = $trip->started_at->diffInMinutes($trip->completed_at);

                if ($minutes <= 0) {
                    return null;
                }

                return ((float) $trip->actual_distance / ($minutes / 60));
            })
            ->filter();

        if ($durations->isEmpty()) {
            return null;
        }

        return round((float) $durations->avg(), 2);
    }

    private function describeRoadCondition(int $trafficLevel): string
    {
        return match (true) {
            $trafficLevel <= 1 => 'clear',
            $trafficLevel === 2 => 'light',
            $trafficLevel === 3 => 'moderate',
            $trafficLevel === 4 => 'heavy',
            default => 'critical',
        };
    }

    private function calculateTripQualityScore(?DriverBehavior $driverBehavior, ?PassengerBehavior $passengerBehavior, ?RouteState $routeState, ?WeatherCondition $weatherCondition): ?float
    {
        $components = [];

        if ($driverBehavior?->driving_score !== null) {
            $components[] = (float) $driverBehavior->driving_score;
        }

        if ($passengerBehavior?->reliability_score !== null) {
            $components[] = (float) $passengerBehavior->reliability_score;
        }

        if ($routeState?->traffic_level !== null) {
            $components[] = 1 - ((float) $routeState->traffic_level / 5);
        }

        if ($weatherCondition?->weather_factor !== null) {
            $components[] = (float) $weatherCondition->weather_factor;
        }

        return $this->compositeScore($components);
    }

    private function calculateEtaDeviationMinutes(Trip $trip, ?RouteState $routeState): ?int
    {
        if (!$routeState?->estimated_duration_min || !$trip->started_at || !$trip->completed_at) {
            return null;
        }

        $actualMinutes = $trip->started_at->diffInMinutes($trip->completed_at);

        return (int) round($actualMinutes - (int) $routeState->estimated_duration_min);
    }

    private function compositeScore(array $components): ?float
    {
        $values = collect($components)->filter(fn ($value) => $value !== null)->map(fn ($value) => max(0.0, min(1.0, (float) $value)));

        if ($values->isEmpty()) {
            return null;
        }

        return round((float) $values->avg(), 4);
    }

    private function getNearestTrafficEvent(float $lat, float $lng): array
    {
        if (!Schema::hasTable('traffic_events')) {
            return [];
        }

        $query = DB::table('traffic_events')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        $query->whereBetween('latitude', [$lat - 0.08, $lat + 0.08])
              ->whereBetween('longitude', [$lng - 0.08, $lng + 0.08]);

        $event = $query->orderByDesc('event_time')->first();

        if (!$event) {
            return [];
        }

        return [
            'traffic_level' => (int) ($event->traffic_level ?? 3),
            'weather_factor' => isset($event->weather_factor) ? (float) $event->weather_factor : 1.0,
            'temperature_celsius' => isset($event->temperature_celsius) ? (float) $event->temperature_celsius : 24.0,
            'wind_speed_kmh' => isset($event->wind_speed_kmh) ? (float) $event->wind_speed_kmh : 8.0,
            'precipitation_mm' => isset($event->precipitation_mm) ? (float) $event->precipitation_mm : 0.0,
            'event_type' => $event->event_type,
            'congestion_index' => max(0.0, min(1.0, ($event->traffic_level ?? 3) / 5)),
            'description' => sprintf('Traffic event snapshot %s', $event->event_type ?? 'unknown'),
        ];
    }

    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        if ($lat1 === 0.0 && $lng1 === 0.0 && $lat2 === 0.0 && $lng2 === 0.0) {
            return 0.0;
        }

        $earthRadiusKm = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadiusKm * $c, 3);
    }
}
