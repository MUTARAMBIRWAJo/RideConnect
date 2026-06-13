<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\MobileUser;
use App\Models\MotorcycleTrip;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Trip;
use App\Models\User;
use Tests\TestCase;

class MobilePaymentAndRatingFlowTest extends TestCase
{
    public function test_passenger_can_create_and_fetch_trip_payment_with_schema_status(): void
    {
        $passenger = User::factory()->create([
            'role' => 'PASSENGER',
            'is_approved' => true,
        ]);

        $trip = Trip::factory()->create([
            'passenger_id' => $passenger->id,
            'status' => 'COMPLETED',
            'booking_id' => null,
        ]);

        $create = $this->actingAs($passenger, 'sanctum')->postJson('/api/v1/passenger/payments', [
            'type' => 'trip',
            'trip_id' => $trip->id,
            'amount' => 2500,
            'currency' => 'RWF',
            'payment_method' => 'mobile_money',
            'transaction_id' => 'momo-test-'.uniqid(),
        ]);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $paymentId = $create->json('data.id');
        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'trip_id' => $trip->id,
            'booking_id' => null,
            'status' => 'pending',
        ]);

        $this->actingAs($passenger, 'sanctum')
            ->getJson("/api/v1/passenger/payments/{$paymentId}")
            ->assertOk()
            ->assertJsonPath('data.id', $paymentId)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_motorcycle_payment_and_rating_update_driver_metrics(): void
    {
        $passenger = User::factory()->create([
            'role' => 'PASSENGER',
            'is_approved' => true,
        ]);
        $driver = Driver::factory()->create([
            'rating' => 0,
            'rating_count' => 0,
        ]);

        $trip = MotorcycleTrip::query()->create([
            'passenger_id' => $passenger->id,
            'driver_id' => $driver->id,
            'pickup_location' => 'Kigali Heights',
            'pickup_lat' => -1.9440,
            'pickup_lng' => 30.0618,
            'dropoff_location' => 'Kigali Convention Centre',
            'dropoff_lat' => -1.9536,
            'dropoff_lng' => 30.0928,
            'estimated_fare' => 1200,
            'actual_fare' => 1200,
            'currency' => 'RWF',
            'status' => 'COMPLETED',
            'requested_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        $payment = $this->actingAs($passenger, 'sanctum')->postJson('/api/v1/passenger/payments', [
            'type' => 'motor_vehicle',
            'motorcycle_trip_id' => $trip->id,
            'amount' => 1200,
            'currency' => 'RWF',
            'payment_method' => 'cash',
        ]);

        $payment->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('payments', [
            'type' => 'motor_vehicle',
            'motorcycle_trip_id' => $trip->id,
            'booking_id' => null,
            'status' => 'pending',
        ]);

        Review::query()->create([
            'user_id' => User::factory()->create()->id,
            'driver_id' => $driver->id,
            'motorcycle_trip_id' => MotorcycleTrip::query()->create([
                'passenger_id' => User::factory()->create()->id,
                'driver_id' => $driver->id,
                'pickup_location' => 'Nyamirambo',
                'pickup_lat' => -1.98,
                'pickup_lng' => 30.03,
                'dropoff_location' => 'Remera',
                'dropoff_lat' => -1.95,
                'dropoff_lng' => 30.10,
                'estimated_fare' => 1400,
                'currency' => 'RWF',
                'status' => 'COMPLETED',
            ])->id,
            'rating' => 3,
            'reviewer_type' => 'passenger',
            'is_public' => false,
        ]);

        $this->actingAs($passenger, 'sanctum')
            ->postJson("/api/v1/passenger/motor-vehicle/trip-requests/{$trip->id}/rate", [
                'rating' => 5,
                'comment' => 'Clean helmet and fast pickup',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $driver->refresh();
        $this->assertSame(2, $driver->rating_count);
        $this->assertSame('4.00', (string) $driver->rating);
    }

    public function test_motorcycle_matching_metrics_are_mass_assignable_and_cast(): void
    {
        $trip = MotorcycleTrip::query()->create([
            'passenger_id' => User::factory()->create()->id,
            'pickup_location' => 'Kigali Heights',
            'pickup_lat' => -1.9440,
            'pickup_lng' => 30.0618,
            'dropoff_location' => 'Kigali Convention Centre',
            'dropoff_lat' => -1.9536,
            'dropoff_lng' => 30.0928,
            'estimated_fare' => 1200,
            'currency' => 'RWF',
            'status' => 'MATCHING',
        ]);

        $trip->update([
            'matching_duration_seconds' => 9,
            'candidate_count' => 4,
            'matched_via' => 'fast_local',
            'matching_metadata' => ['score' => 0.91],
        ]);

        $trip->refresh();
        $this->assertSame(9, $trip->matching_duration_seconds);
        $this->assertSame(4, $trip->candidate_count);
        $this->assertSame('fast_local', $trip->matched_via);
        $this->assertSame(['score' => 0.91], $trip->matching_metadata);
    }
}
