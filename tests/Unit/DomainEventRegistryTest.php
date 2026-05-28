<?php

namespace Tests\Unit;

use App\Domain\Core\DomainEventRegistry;
use App\Events\Domain\BookingCreated;
use App\Events\Domain\RideCreated;
use App\Events\Domain\TripCompleted;
use App\Events\Domain\TripMatched;
use App\Events\Domain\TripStarted;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DomainEventRegistryTest extends TestCase
{
    #[Test]
    public function it_registers_expected_event_listener_contracts(): void
    {
        $listeners = DomainEventRegistry::listeners();

        $this->assertArrayHasKey(RideCreated::class, $listeners);
        $this->assertArrayHasKey(BookingCreated::class, $listeners);
        $this->assertArrayHasKey(TripMatched::class, $listeners);
        $this->assertArrayHasKey(TripStarted::class, $listeners);
        $this->assertArrayHasKey(TripCompleted::class, $listeners);

        foreach ($listeners as $eventClass => $eventListeners) {
            $this->assertNotEmpty($eventListeners, $eventClass.' should have at least one listener');
        }
    }
}
