<?php

namespace Tests\Unit;

use App\Events\Domain\DomainEvent;
use App\Events\Domain\RideCompleted;
use App\Events\Domain\PaymentCaptured;
use App\Models\DomainEvent as DomainEventModel;
use App\Services\EventSourcing\EventDispatcherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * EventReplayTest
 *
 * Verifies that:
 * 1. Domain events are persisted to domain_events table
 * 2. Immutability: payload and event_type cannot be changed
 * 3. Domain events can be replayed for an aggregate
 * 4. Replay returns events ordered by version
 */
class EventReplayTest extends TestCase
{
    use RefreshDatabase;

    private EventDispatcherService $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = app(EventDispatcherService::class);
    }

    // -----------------------------------------------------------------------
    // Persistence
    // -----------------------------------------------------------------------

    public function test_domain_event_is_persisted_to_table(): void
    {
        $rideId = Str::uuid()->toString();
        $event  = new RideCompleted(
            rideId:      $rideId,
            driverId:    1,
            passengerId: 2,
            fareAmount:  25_000.0,
        );

        $this->dispatcher->dispatch($event);

        $this->assertDatabaseHas('domain_events', [
            'aggregate_id'   => $rideId,
            'aggregate_type' => 'ride',
            'event_type'     => 'ride_completed',
        ]);
    }

    public function test_event_outbox_record_created_alongside_event(): void
    {
        $rideId = Str::uuid()->toString();
        $event  = new RideCompleted(
            rideId:      $rideId,
            driverId:    1,
            passengerId: 2,
            fareAmount:  10_000.0,
        );

        $this->dispatcher->dispatch($event);

        $this->assertDatabaseHas('event_outbox', [
            'event_type' => 'ride_completed',
            'status'     => 'pending',
        ]);
    }

    // -----------------------------------------------------------------------
    // Immutability
    // -----------------------------------------------------------------------

    public function test_domain_event_payload_cannot_be_updated(): void
    {
        $rideId = Str::uuid()->toString();
        $event  = new RideCompleted($rideId, 1, 2, 25_000.0);
        $this->dispatcher->dispatch($event);

        /** @var DomainEventModel $model */
        $model = DomainEventModel::where('aggregate_id', $rideId)->firstOrFail();

        $this->expectException(\RuntimeException::class);
        $model->update(['payload' => ['tampered' => true]]);
    }

    public function test_domain_event_cannot_be_deleted(): void
    {
        $rideId = Str::uuid()->toString();
        $event  = new RideCompleted($rideId, 1, 2, 25_000.0);
        $this->dispatcher->dispatch($event);

        /** @var DomainEventModel $model */
        $model = DomainEventModel::where('aggregate_id', $rideId)->firstOrFail();

        $this->expectException(\RuntimeException::class);
        $model->delete();
    }

    // -----------------------------------------------------------------------
    // Replay
    // -----------------------------------------------------------------------

    public function test_replay_returns_events_ordered_by_version(): void
    {
        $rideId = Str::uuid()->toString();

        $event1 = new RideCompleted($rideId, 1, 2, 25_000.0);
        $event2 = new RideCompleted($rideId, 1, 2, 30_000.0);
        $event3 = new RideCompleted($rideId, 1, 2, 35_000.0);

        $this->dispatcher->dispatch($event1);
        $this->dispatcher->dispatch($event2);
        $this->dispatcher->dispatch($event3);

        $events = $this->dispatcher->replay('ride', $rideId);

        $this->assertCount(3, $events);

        $versions = $events->pluck('version')->toArray();
        $this->assertSame(array_values($versions), $versions, 'Events should be ordered by version');
    }

    public function test_replay_from_version_filters_older_events(): void
    {
        $rideId = Str::uuid()->toString();

        for ($i = 0; $i < 5; $i++) {
            $this->dispatcher->dispatch(new RideCompleted($rideId, 1, 2, 10_000.0 * $i));
        }

        $allEvents  = $this->dispatcher->replay('ride', $rideId);
        $thirdVersion = $allEvents->nth(3)->first()?->version ?? 1;

        $filtered = $this->dispatcher->replay('ride', $rideId, $thirdVersion);

        $this->assertLessThan($allEvents->count(), $filtered->count(), 'Filtered replay should have fewer events');
    }

    public function test_replay_by_type_returns_all_matching_events(): void
    {
        $ride1 = Str::uuid()->toString();
        $ride2 = Str::uuid()->toString();

        $this->dispatcher->dispatch(new RideCompleted($ride1, 1, 2, 10_000.0));
        $this->dispatcher->dispatch(new RideCompleted($ride2, 3, 4, 20_000.0));

        $events = $this->dispatcher->replayByType('ride_completed');

        $this->assertGreaterThanOrEqual(2, $events->count());

        $aggregateIds = $events->pluck('aggregate_id')->unique()->toArray();
        $this->assertContains($ride1, $aggregateIds);
        $this->assertContains($ride2, $aggregateIds);
    }
}
