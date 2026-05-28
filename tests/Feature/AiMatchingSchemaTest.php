<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\MobileUser;
use App\Models\PassengerBehavior;
use App\Models\Trip;
use App\Models\User;
use App\Services\TripConditionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiMatchingSchemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_exposes_the_ai_matching_columns(): void
    {
        $this->assertTrue(Schema::hasTable('driver_behaviors'));
        $this->assertTrue(Schema::hasColumn('driver_behaviors', 'driving_score'));
        $this->assertTrue(Schema::hasColumn('passenger_behaviors', 'user_id'));
        $this->assertTrue(Schema::hasColumn('passenger_behaviors', 'payment_reliability'));
        $this->assertTrue(Schema::hasColumn('route_states', 'route_id'));
        $this->assertTrue(Schema::hasColumn('route_states', 'road_condition'));
        $this->assertTrue(Schema::hasColumn('weather_conditions', 'weather_type'));
        $this->assertTrue(Schema::hasColumn('weather_conditions', 'visibility'));
        $this->assertTrue(Schema::hasColumn('trips', 'trip_quality_score'));
        $this->assertTrue(Schema::hasColumn('trips', 'eta_deviation_minutes'));
    }

    #[Test]
    public function it_preserves_historical_snapshots_across_multiple_trips(): void
    {
        $service = app(TripConditionService::class);

        $driver = Driver::factory()->create(['rating' => 4.8]);

        $mobileUser = MobileUser::factory()->create(['role' => 'PASSENGER']);
        User::factory()->create(['mobile_user_id' => $mobileUser->id]);

        $firstTrip = Trip::factory()->create([
            'driver_id' => $driver->id,
            'passenger_id' => $mobileUser->id,
            'ride_id' => null,
            'pickup_lat' => -1.95,
            'pickup_lng' => 30.06,
            'dropoff_lat' => -1.96,
            'dropoff_lng' => 30.07,
            'requested_at' => now()->subHour(),
            'accepted_at' => now()->subMinutes(50),
            'started_at' => now()->subMinutes(45),
            'completed_at' => now()->subMinutes(10),
        ]);

        $firstTrip->load('ride');
        $service->captureSnapshot($firstTrip);

        $secondTrip = Trip::factory()->create([
            'driver_id' => $driver->id,
            'passenger_id' => $mobileUser->id,
            'pickup_lat' => -1.94,
            'pickup_lng' => 30.05,
            'dropoff_lat' => -1.97,
            'dropoff_lng' => 30.08,
            'requested_at' => now()->subMinutes(30),
            'accepted_at' => now()->subMinutes(25),
            'started_at' => now()->subMinutes(20),
            'completed_at' => now()->subMinutes(5),
        ]);

        $service->captureSnapshot($secondTrip);

        $this->assertDatabaseCount('driver_behaviors', 2);
        $this->assertDatabaseCount('passenger_behaviors', 2);
        $this->assertDatabaseCount('route_states', 2);
        $this->assertDatabaseCount('weather_conditions', 2);

        $this->assertNotNull($firstTrip->fresh()->driver_behavior_id);
        $this->assertNotNull($secondTrip->fresh()->driver_behavior_id);
        $this->assertNotNull($firstTrip->fresh()->passenger_behavior_id);
        $this->assertNotNull($secondTrip->fresh()->passenger_behavior_id);
        $this->assertNotNull($firstTrip->fresh()->trip_quality_score);
    }

    #[Test]
    public function it_keeps_the_relationship_bridge_between_mobile_users_and_users(): void
    {
        $mobileUser = MobileUser::factory()->create(['role' => 'PASSENGER']);
        $user = User::factory()->create(['mobile_user_id' => $mobileUser->id]);

        $behavior = PassengerBehavior::query()->create([
            'user_id' => $user->id,
            'passenger_id' => $mobileUser->id,
            'rating' => 4.5,
            'cancellation_rate' => 0.1,
            'no_show_rate' => 0.05,
            'payment_reliability' => 0.95,
            'reliability_score' => 0.9,
            'total_trips' => 12,
        ]);

        $this->assertSame($user->id, $behavior->user->id);
        $this->assertSame($mobileUser->id, $behavior->passenger->id);
    }
}
