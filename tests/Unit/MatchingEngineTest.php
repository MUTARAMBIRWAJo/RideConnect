<?php

namespace Tests\Unit;

use App\Models\Driver;
use App\Models\DriverBehavior;
use App\Models\MobileUser;
use App\Models\PassengerBehavior;
use App\Models\RouteState;
use App\Models\Trip;
use App\Models\User;
use App\Models\WeatherCondition;
use App\Services\MatchingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchingEngineTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_prefers_closer_and_more_reliable_drivers(): void
    {
        $mobileUser = MobileUser::factory()->create(['role' => 'PASSENGER']);
        $user = User::factory()->create(['mobile_user_id' => $mobileUser->id]);

        PassengerBehavior::query()->create([
            'user_id' => $user->id,
            'passenger_id' => $mobileUser->id,
            'rating' => 4.8,
            'cancellation_rate' => 0.05,
            'no_show_rate' => 0.0,
            'payment_reliability' => 0.98,
            'reliability_score' => 0.95,
            'total_trips' => 20,
        ]);

        $trip = Trip::factory()->create([
            'passenger_id' => $mobileUser->id,
            'pickup_lat' => -1.95,
            'pickup_lng' => 30.06,
            'dropoff_lat' => -1.97,
            'dropoff_lng' => 30.08,
        ]);

        $nearDriver = Driver::factory()->create([
            'current_latitude' => -1.95,
            'current_longitude' => 30.06,
            'rating' => 4.9,
        ]);
        $farDriver = Driver::factory()->create([
            'current_latitude' => -1.75,
            'current_longitude' => 30.25,
            'rating' => 3.8,
        ]);

        DriverBehavior::query()->create([
            'driver_id' => $nearDriver->id,
            'rating' => 4.9,
            'acceptance_rate' => 0.98,
            'cancellation_rate' => 0.01,
            'on_time_rate' => 0.97,
            'driving_score' => 0.96,
        ]);

        DriverBehavior::query()->create([
            'driver_id' => $farDriver->id,
            'rating' => 3.7,
            'acceptance_rate' => 0.55,
            'cancellation_rate' => 0.25,
            'on_time_rate' => 0.6,
            'driving_score' => 0.58,
        ]);

        $trip->forceFill([
            'route_state_id' => RouteState::query()->create([
                'trip_id' => $trip->id,
                'traffic_level' => 2,
                'road_condition' => 'light',
                'average_speed' => 33.5,
                'incident_flag' => false,
            ])->id,
            'weather_condition_id' => WeatherCondition::query()->create([
                'trip_id' => $trip->id,
                'weather_type' => null,
                'temperature' => null,
                'rain_intensity' => null,
                'visibility' => null,
                'wind_speed' => null,
            ])->id,
        ])->save();

        $scores = app(MatchingEngine::class)->calculateMatchingScore($trip->fresh(['passenger', 'routeState', 'weatherCondition']), [$farDriver->id, $nearDriver->id]);

        $this->assertArrayHasKey($nearDriver->id, $scores);
        $this->assertArrayHasKey($farDriver->id, $scores);
        $this->assertGreaterThan($scores[$farDriver->id], $scores[$nearDriver->id]);
    }

    /** @test */
    public function it_remains_null_safe_when_snapshot_data_is_missing(): void
    {
        $trip = Trip::factory()->create();

        $scores = app(MatchingEngine::class)->calculateMatchingScore($trip, [Driver::factory()->create()->id]);

        $this->assertIsArray($scores);
        $this->assertCount(1, $scores);
        $this->assertIsFloat(array_values($scores)[0]);
    }
}
