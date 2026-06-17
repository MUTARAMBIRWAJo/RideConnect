<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_on_multiple_devices_simultaneously()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
            'role' => 'PASSENGER',
            'is_approved' => true,
        ]);

        // Login Device A
        $responseA = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
            'device_name' => 'Device_A',
        ]);
        $responseA->assertStatus(200);
        $tokenA = $responseA->json('data.token');

        // Login Device B
        $responseB = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
            'device_name' => 'Device_B',
        ]);
        $responseB->assertStatus(200);
        $tokenB = $responseB->json('data.token');

        $this->assertNotEquals($tokenA, $tokenB);

        // Verify Device A is still authenticated
        $this->withToken($tokenA)->getJson('/api/v1/auth/profile')->assertStatus(200);
        
        // Verify Device B is authenticated
        $this->withToken($tokenB)->getJson('/api/v1/auth/profile')->assertStatus(200);
    }

    public function test_logout_only_revokes_current_device_token()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
            'role' => 'PASSENGER',
            'is_approved' => true,
        ]);

        $tokenA = $user->createToken('Device_A')->plainTextToken;
        $tokenB = $user->createToken('Device_B')->plainTextToken;

        // Logout from Device A
        $this->withToken($tokenA)->postJson('/api/v1/auth/logout')->assertStatus(200);

        // Device A token is invalid (deleted from DB)
        $this->assertDatabaseMissing('personal_access_tokens', [
            'name' => 'Device_A'
        ]);

        // Device B token is still valid (remains in DB)
        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'Device_B'
        ]);
    }

    public function test_heartbeat_updates_online_status()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
            'role' => 'PASSENGER',
            'is_approved' => true,
            'is_online' => false,
        ]);

        $token = $user->createToken('Device_A')->plainTextToken;

        // Any authenticated request triggers the HeartbeatMiddleware
        $this->withToken($token)->getJson('/api/v1/auth/profile')->assertStatus(200);

        $user->refresh();
        $this->assertTrue((bool) $user->is_online);
        $this->assertNotNull($user->last_seen_at);
    }
}
