<?php

namespace Tests\Feature\V3;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_can_update_location_and_view_live_and_history(): void
    {
        $driverUser = User::factory()->create(['role' => \App\Enums\UserRole::DRIVER->value]);
        $driver = Driver::factory()->create(['user_id' => $driverUser->id]);

        // 1. Update location
        $response = $this->actingAs($driverUser, 'sanctum')
            ->postJson('/api/v3/location/update', [
                'latitude' => -1.9441,
                'longitude' => 30.0619,
                'heading' => 90,
                'speed' => 22.5,
                'accuracy' => 5.0,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Assert database updates
        $this->assertDatabaseHas('driver_locations_v3', [
            'driver_id' => $driver->id,
            'latitude' => -1.9441,
            'longitude' => 30.0619,
            'heading' => 90,
            'speed' => 22.5,
        ]);

        $this->assertDatabaseHas('driver_locations', [
            'driver_id' => $driver->id,
            'latitude' => -1.9441,
            'longitude' => 30.0619,
            'heading' => 90,
            'speed' => 22.5,
            'accuracy' => 5.0,
        ]);

        $this->assertDatabaseHas('location_histories', [
            'user_id' => $driverUser->id,
            'role' => 'driver',
            'latitude' => -1.9441,
            'longitude' => 30.0619,
        ]);

        // 2. Get Live Location
        $liveResponse = $this->actingAs($driverUser, 'sanctum')
            ->getJson("/api/v3/location/live/{$driverUser->id}");

        $liveResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.latitude', -1.9441)
            ->assertJsonPath('data.longitude', 30.0619)
            ->assertJsonPath('data.role', 'driver');

        // 3. Get Location History
        $historyResponse = $this->actingAs($driverUser, 'sanctum')
            ->getJson("/api/v3/location/history/{$driverUser->id}");

        $historyResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.latitude', -1.9441)
            ->assertJsonPath('data.0.longitude', 30.0619);
    }

    public function test_passenger_can_update_location_and_view_live_and_history(): void
    {
        $passengerUser = User::factory()->create(['role' => \App\Enums\UserRole::PASSENGER->value]);

        // 1. Update location
        $response = $this->actingAs($passengerUser, 'sanctum')
            ->postJson('/api/v3/location/update', [
                'latitude' => -1.9536,
                'longitude' => 30.0606,
                'heading' => 180,
                'speed' => 0.0,
                'accuracy' => 3.5,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Assert database updates
        $this->assertDatabaseHas('passenger_locations', [
            'user_id' => $passengerUser->id,
            'latitude' => -1.9536,
            'longitude' => 30.0606,
            'heading' => 180,
            'speed' => 0.0,
            'accuracy' => 3.5,
        ]);

        $this->assertDatabaseHas('location_histories', [
            'user_id' => $passengerUser->id,
            'role' => 'passenger',
            'latitude' => -1.9536,
            'longitude' => 30.0606,
        ]);

        // 2. Get Live Location
        $liveResponse = $this->actingAs($passengerUser, 'sanctum')
            ->getJson("/api/v3/location/live/{$passengerUser->id}");

        $liveResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.latitude', -1.9536)
            ->assertJsonPath('data.longitude', 30.0606)
            ->assertJsonPath('data.role', 'passenger');

        // 3. Get Location History
        $historyResponse = $this->actingAs($passengerUser, 'sanctum')
            ->getJson("/api/v3/location/history/{$passengerUser->id}");

        $historyResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.latitude', -1.9536)
            ->assertJsonPath('data.0.longitude', 30.0606);
    }

    public function test_location_history_is_throttled_to_five_minutes(): void
    {
        $passengerUser = User::factory()->create(['role' => \App\Enums\UserRole::PASSENGER->value]);

        // 1. Update location first time - should insert to history
        $response1 = $this->actingAs($passengerUser, 'sanctum')
            ->postJson('/api/v3/location/update', [
                'latitude' => -1.9536,
                'longitude' => 30.0606,
            ]);
        $response1->assertStatus(200);
        $this->assertEquals(1, \App\Models\LocationHistory::where('user_id', $passengerUser->id)->count());

        // 2. Update location second time immediately - should NOT insert to history
        $response2 = $this->actingAs($passengerUser, 'sanctum')
            ->postJson('/api/v3/location/update', [
                'latitude' => -1.9540,
                'longitude' => 30.0610,
            ]);
        $response2->assertStatus(200);
        $this->assertEquals(1, \App\Models\LocationHistory::where('user_id', $passengerUser->id)->count());

        // 3. Manually update the created_at of the first history record to be 6 minutes ago
        $history = \App\Models\LocationHistory::where('user_id', $passengerUser->id)->first();
        $history->update(['created_at' => now()->subMinutes(6)]);

        // 4. Update location third time - should insert to history since 6 minutes has passed
        $response3 = $this->actingAs($passengerUser, 'sanctum')
            ->postJson('/api/v3/location/update', [
                'latitude' => -1.9550,
                'longitude' => 30.0620,
            ]);
        $response3->assertStatus(200);
        $this->assertEquals(2, \App\Models\LocationHistory::where('user_id', $passengerUser->id)->count());
    }

    public function test_logout_saves_last_known_location_permanently(): void
    {
        $passengerUser = User::factory()->create([
            'role' => \App\Enums\UserRole::PASSENGER->value,
            'is_approved' => true,
        ]);

        $token = $passengerUser->createToken('TestDevice')->plainTextToken;

        // Setup current location first
        \App\Models\PassengerLocation::create([
            'user_id' => $passengerUser->id,
            'lat' => -1.9536,
            'lng' => 30.0606,
            'latitude' => -1.9536,
            'longitude' => 30.0606,
            'heading' => 180,
            'speed' => 0.0,
            'is_online' => true,
            'recorded_at' => now(),
        ]);

        // Clean out history
        \App\Models\LocationHistory::where('user_id', $passengerUser->id)->delete();

        // Perform logout
        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200);

        // Verify history has a record with the correct coordinates
        $this->assertDatabaseHas('location_histories', [
            'user_id' => $passengerUser->id,
            'role' => 'passenger',
            'latitude' => -1.9536,
            'longitude' => 30.0606,
        ]);
    }
}
