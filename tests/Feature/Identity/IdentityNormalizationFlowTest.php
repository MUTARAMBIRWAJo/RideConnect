<?php

namespace Tests\Feature\Identity;

use App\Models\Driver;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Trip;
use App\Models\User;
use App\Services\Identity\IdentityConsistencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdentityNormalizationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_passenger_register_trip_pay_rate_flow_uses_canonical_user_ids(): void
    {
        $register = $this->postJson('/api/v1/auth/register/passenger', [
            'name' => 'Jane Passenger',
            'email' => 'jane-passenger@rideconnect.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+250788000001',
            'role' => 'PASSENGER',
        ]);

        $register->assertCreated();
        $passenger = User::query()->where('email', 'jane-passenger@rideconnect.test')->firstOrFail();
        $passenger->update(['is_approved' => true]);

        $driverUser = User::factory()->create(['role' => 'DRIVER', 'is_approved' => true]);
        $driver = Driver::factory()->create(['user_id' => $driverUser->id]);

        $trip = Trip::factory()->create([
            'passenger_id' => $passenger->id,
            'driver_id' => $driver->id,
            'status' => 'COMPLETED',
            'booking_id' => null,
        ]);

        $paymentResponse = $this->actingAs($passenger, 'sanctum')->postJson('/api/v1/passenger/payments', [
            'type' => 'trip',
            'trip_id' => $trip->id,
            'amount' => 2500,
            'currency' => 'RWF',
            'payment_method' => 'mobile_money',
            'transaction_id' => 'identity-flow-'.uniqid(),
        ]);

        $paymentResponse->assertCreated();
        $this->assertDatabaseHas('payments', [
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
        ]);

        Review::query()->create([
            'user_id' => $passenger->id,
            'driver_id' => $driver->id,
            'ride_id' => $trip->ride_id ?? 1,
            'booking_id' => $trip->booking_id ?? 1,
            'rating' => 5,
            'comment' => 'Great ride',
        ]);

        $report = app(IdentityConsistencyService::class)->generateReport();

        $this->assertSame(0, $report['checks']['orphan_trips_passenger']['count']);
        $this->assertSame(0, $report['checks']['orphan_payments_user']['count']);
        $this->assertSame((int) $passenger->id, (int) Trip::find($trip->id)->passenger_id);
        $this->assertSame((int) $passenger->id, (int) Payment::where('trip_id', $trip->id)->value('user_id'));
    }

    public function test_driver_accept_complete_flow_keeps_driver_user_linkage(): void
    {
        $driverUser = User::factory()->create(['role' => 'DRIVER', 'is_approved' => true]);
        $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
        $passenger = User::factory()->create(['role' => 'PASSENGER', 'is_approved' => true]);

        $trip = Trip::factory()->pending()->create([
            'passenger_id' => $passenger->id,
            'driver_id' => null,
        ]);

        $accept = $this->actingAs($driverUser, 'sanctum')->putJson("/api/v1/driver/trips/{$trip->id}/accept");
        $accept->assertOk();

        $trip->refresh();
        $this->assertSame((int) $driver->id, (int) $trip->driver_id);

        $complete = $this->actingAs($driverUser, 'sanctum')->putJson("/api/v1/driver/trips/{$trip->id}/complete", [
            'actual_fare' => $trip->fare,
            'actual_distance' => 5.2,
        ]);
        $complete->assertOk();

        $report = app(IdentityConsistencyService::class)->generateReport();
        $this->assertSame(0, $report['checks']['orphan_trips_driver']['count']);
    }
}
