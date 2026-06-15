<?php

namespace Tests\Unit\Identity;

use App\Models\Driver;
use App\Models\MobileUser;
use App\Models\Payment;
use App\Models\Trip;
use App\Models\User;
use App\Services\Identity\IdentityConsistencyService;
use App\Services\Identity\IdentityResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdentityServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_passenger_owner_id_is_canonical_users_id(): void
    {
        $mobile = MobileUser::factory()->create(['role' => 'PASSENGER']);
        $user = User::factory()->create([
            'role' => 'PASSENGER',
            'mobile_user_id' => $mobile->id,
            'is_approved' => true,
        ]);

        $resolver = app(IdentityResolverService::class);

        $this->assertSame((int) $user->id, $resolver->passengerOwnerId($user));
        $this->assertContains((int) $mobile->id, $resolver->passengerOwnerIdsForQuery($user));
        $this->assertTrue($resolver->userOwnsPassengerReference($user, (int) $mobile->id));
    }

    public function test_resolve_passenger_user_id_from_legacy_mobile_reference(): void
    {
        $mobile = MobileUser::factory()->create(['role' => 'PASSENGER']);
        $user = User::factory()->create([
            'role' => 'PASSENGER',
            'mobile_user_id' => $mobile->id,
        ]);

        $resolver = app(IdentityResolverService::class);

        $this->assertSame((int) $user->id, $resolver->resolvePassengerUserId((int) $mobile->id));
    }

    public function test_driver_user_id_resolves_from_driver_profile(): void
    {
        $user = User::factory()->create(['role' => 'DRIVER', 'is_approved' => true]);
        $driver = Driver::factory()->create(['user_id' => $user->id]);

        $resolver = app(IdentityResolverService::class);

        $this->assertSame((int) $user->id, $resolver->driverUserIdFromDriverId((int) $driver->id));
    }

    public function test_orphan_trip_passenger_is_detected(): void
    {
        MobileUser::factory()->create([
            'id' => 999999,
            'role' => 'PASSENGER',
            'email' => 'orphan_mobile@example.com',
            'phone' => '+250700000999'
        ]);
        Trip::factory()->create(['passenger_id' => 999999]);

        $report = app(IdentityConsistencyService::class)->generateReport();

        $this->assertGreaterThan(0, $report['checks']['orphan_trips_passenger']['count']);
    }

    public function test_valid_identity_graph_scores_high(): void
    {
        MobileUser::factory()->create([
            'id' => 888888,
            'role' => 'PASSENGER',
            'email' => 'passenger_mobile@example.com',
            'phone' => '+250700000888'
        ]);
        $passenger = User::factory()->create([
            'id' => 888888,
            'role' => 'PASSENGER',
            'is_approved' => true,
            'mobile_user_id' => 888888,
            'email' => 'passenger_canonical@example.com',
            'phone' => '+250700000111'
        ]);

        $driverUser = User::factory()->create([
            'role' => 'DRIVER',
            'is_approved' => true,
            'email' => 'driver_canonical@example.com',
            'phone' => '+250700000222'
        ]);
        $driver = Driver::factory()->create(['user_id' => $driverUser->id]);

        $trip = Trip::factory()->create([
            'passenger_id' => $passenger->id,
            'driver_id' => $driver->id,
        ]);

        Payment::query()->create([
            'user_id' => $passenger->id,
            'trip_id' => $trip->id,
            'type' => 'trip',
            'amount' => 1000,
            'currency' => 'RWF',
            'status' => 'pending',
        ]);

        $report = app(IdentityConsistencyService::class)->generateReport();

        $this->assertGreaterThanOrEqual(85, $report['summary']['production_readiness_score']);
    }
}
