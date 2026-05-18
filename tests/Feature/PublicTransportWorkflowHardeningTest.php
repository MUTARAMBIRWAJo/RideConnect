<?php

namespace Tests\Feature;

use App\Exceptions\DomainException;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\MobileUser;
use App\Models\Payment;
use App\Models\Ride;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Services\PublicTransportAvailabilityService;
use App\Services\SeatReservationService;
use App\Services\TripCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicTransportWorkflowHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_bus_seat_reservation_decrements_once_and_prevents_oversell(): void
    {
        $ride = $this->busRide(['available_seats' => 3]);
        $passenger = MobileUser::factory()->create();

        $reservation = app(SeatReservationService::class)->reserveForBooking($ride->id, 2, $passenger->id);

        $this->assertSame('reserved', $reservation->status);
        $this->assertSame(1, $ride->fresh()->available_seats);

        $this->expectException(DomainException::class);
        app(SeatReservationService::class)->reserveForBooking($ride->id, 2, $passenger->id);
    }

    public function test_moto_busy_driver_is_excluded_from_visibility(): void
    {
        $driver = Driver::factory()->create(['availability_status' => 'available']);
        $vehicle = Vehicle::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_type' => 'motorcycle',
            'is_active' => true,
            'maintenance_status' => 'operational',
        ]);
        $ride = Ride::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'transport_type' => Ride::TRANSPORT_MOTORCYCLE,
            'travel_mode' => Ride::MODE_ON_DEMAND,
            'status' => 'published',
            'available_seats' => 1,
        ]);

        Trip::factory()->create([
            'ride_id' => $ride->id,
            'driver_id' => $driver->id,
            'transport_type' => Ride::TRANSPORT_MOTORCYCLE,
            'status' => 'ACCEPTED',
            'payment_status' => 'pending',
            'assignment_status' => 'accepted',
        ]);

        $visible = app(PublicTransportAvailabilityService::class)->availableQuery([
            'transport_type' => Ride::TRANSPORT_MOTORCYCLE,
        ])->pluck('id');

        $this->assertFalse($visible->contains($ride->id));
    }

    public function test_trip_completion_requires_verified_payment(): void
    {
        $trip = Trip::factory()->create([
            'ride_id' => $this->busRide()->id,
            'status' => 'STARTED',
            'payment_status' => 'pending',
            'transport_type' => Ride::TRANSPORT_BUS,
        ]);

        $this->expectException(DomainException::class);
        app(TripCompletionService::class)->complete($trip->id, null);
    }

    public function test_paid_trip_completion_issues_transport_ticket_and_releases_driver(): void
    {
        $driver = Driver::factory()->create(['availability_status' => 'busy']);
        $ride = $this->busRide(['driver_id' => $driver->id]);
        $booking = Booking::factory()->create(['ride_id' => $ride->id]);
        $trip = Trip::factory()->create([
            'booking_id' => $booking->id,
            'ride_id' => $ride->id,
            'driver_id' => $driver->id,
            'status' => 'STARTED',
            'payment_status' => 'paid',
            'transport_type' => Ride::TRANSPORT_BUS,
        ]);

        Payment::query()->create([
            'booking_id' => $booking->id,
            'user_id' => $booking->user_id,
            'amount' => 1000,
            'currency' => 'RWF',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $completed = app(TripCompletionService::class)->complete($trip->id, $driver->id);

        $this->assertSame('COMPLETED', $completed->status);
        $this->assertNotNull($completed->transportTicket);
        $this->assertSame('available', $driver->fresh()->availability_status);
    }

    private function busRide(array $overrides = []): Ride
    {
        $driver = $overrides['driver_id'] ?? Driver::factory()->create(['availability_status' => 'available'])->id;
        $vehicle = Vehicle::factory()->create([
            'driver_id' => $driver,
            'vehicle_type' => 'van',
            'is_active' => true,
            'maintenance_status' => 'operational',
        ]);
        $startZone = DB::table('zones')->insertGetId(['name' => 'Kigali', 'code' => uniqid('kgl-'), 'created_at' => now(), 'updated_at' => now()]);
        $endZone = DB::table('zones')->insertGetId(['name' => 'Musanze', 'code' => uniqid('msz-'), 'created_at' => now(), 'updated_at' => now()]);
        $corridor = DB::table('corridors')->insertGetId([
            'name' => 'Kigali - Musanze',
            'start_zone_id' => $startZone,
            'end_zone_id' => $endZone,
            'base_fare' => 1000,
            'price_per_km' => 50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $route = DB::table('routes')->insertGetId([
            'corridor_id' => $corridor,
            'route_code' => uniqid('RT-'),
            'name' => 'Kigali - Musanze Express',
            'origin' => 'Nyabugogo',
            'destination' => 'Musanze',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Ride::factory()->create(array_merge([
            'driver_id' => $driver,
            'vehicle_id' => $vehicle->id,
            'corridor_id' => $corridor,
            'route_id' => $route,
            'transport_type' => Ride::TRANSPORT_BUS,
            'travel_mode' => Ride::MODE_SCHEDULED,
            'status' => 'published',
            'available_seats' => 10,
        ], $overrides));
    }
}
