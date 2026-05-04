<?php

namespace Tests\Feature;

use App\Events\Domain\TripCompleted;
use App\Events\Domain\TripMatched;
use App\Events\Domain\TripStarted;
use App\Models\Trip;
use App\Models\MobileUser;
use App\Models\Driver;
use App\Services\Location\TripLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Request;
use Tests\TestCase;

class RealtimeBroadcastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('supabase.url', 'https://supabase.test');
        Config::set('supabase.key', 'anon-key');
        Config::set('supabase.anon_key', 'anon-key');
        Config::set('supabase.service_role_key', 'service-key');
    }

    public function test_driver_location_update_triggers_supabase_broadcast(): void
    {
        Http::fake();

        $trip = Trip::factory()->create();
        $service = app(TripLocationService::class);

        $service->updateAndBroadcast($trip->id, -1.95, 30.06);

        Http::assertSent(function (Request $request) use ($trip) {
            return $request->url() === 'https://supabase.test/realtime/v1/broadcast'
                && $request->data()['channel'] === "trip:{$trip->id}"
                && $request->data()['event'] === 'driver.location.updated'
                && $request->data()['payload']['lat'] === -1.95
                && $request->data()['payload']['lng'] === 30.06;
        });
    }

    public function test_trip_matched_triggers_driver_notification(): void
    {
        Http::fake();

        $trip = Trip::factory()->create();
        $driver = Driver::factory()->create();

        event(new TripMatched($trip->id, $driver->id));

        Http::assertSent(function (Request $request) use ($driver, $trip) {
            return $request->url() === 'https://supabase.test/realtime/v1/broadcast'
                && $request->data()['channel'] === "driver:{$driver->id}"
                && $request->data()['event'] === 'trip.request'
                && $request->data()['payload']['trip_id'] === $trip->id;
        });
    }

    public function test_trip_started_triggers_passenger_update(): void
    {
        Http::fake();

        $passenger = MobileUser::factory()->create();
        $trip = Trip::factory()->create(['passenger_id' => $passenger->id]);

        event(new TripStarted($trip->id));

        Http::assertSent(function (Request $request) use ($trip) {
            return $request->url() === 'https://supabase.test/realtime/v1/broadcast'
                && $request->data()['channel'] === "trip:{$trip->id}"
                && $request->data()['event'] === 'trip.started';
        });
    }

    public function test_trip_completed_triggers_both_trip_and_passenger_updates(): void
    {
        Http::fake();

        $passenger = MobileUser::factory()->create();
        $trip = Trip::factory()->create(['passenger_id' => $passenger->id]);

        event(new TripCompleted($trip->id));

        Http::assertSentCount(2);
        Http::assertSent(function (Request $request) use ($trip) {
            return $request->url() === 'https://supabase.test/realtime/v1/broadcast'
                && $request->data()['channel'] === "trip:{$trip->id}"
                && $request->data()['event'] === 'trip.completed';
        });
        Http::assertSent(function (Request $request) use ($trip) {
            return $request->url() === 'https://supabase.test/realtime/v1/broadcast'
                && $request->data()['channel'] === "passenger:{$trip->passenger_id}"
                && $request->data()['event'] === 'trip.completed';
        });
    }
}
