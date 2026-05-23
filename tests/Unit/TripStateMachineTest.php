<?php

namespace Tests\Unit;

use App\Domain\Trip\TripStateMachine;
use App\Exceptions\DomainException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TripStateMachineTest extends TestCase
{
    #[Test]
    public function it_allows_valid_transitions(): void
    {
        $this->assertTrue(TripStateMachine::canTransition('REQUESTED', 'MATCHED'));
        $this->assertTrue(TripStateMachine::canTransition('MATCHED', 'ACCEPTED'));
        $this->assertTrue(TripStateMachine::canTransition('ACCEPTED', 'STARTED'));
        $this->assertTrue(TripStateMachine::canTransition('STARTED', 'COMPLETED'));
        $this->assertTrue(TripStateMachine::canTransition('REQUESTED', 'CANCELLED'));
        $this->assertTrue(TripStateMachine::canTransition('ACCEPTED', 'CANCELLED'));
        $this->assertTrue(TripStateMachine::canTransition('PENDING', 'ACCEPTED')); // legacy support
    }

    #[Test]
    public function it_blocks_invalid_transitions(): void
    {
        $this->assertFalse(TripStateMachine::canTransition('COMPLETED', 'STARTED'));
        $this->assertFalse(TripStateMachine::canTransition('CANCELLED', 'ACCEPTED'));
        $this->assertFalse(TripStateMachine::canTransition('REQUESTED', 'COMPLETED'));
        $this->assertFalse(TripStateMachine::canTransition('STARTED', 'CANCELLED'));
    }

    #[Test]
    public function assert_transition_throws_for_invalid_transition(): void
    {
        $this->expectException(DomainException::class);
        TripStateMachine::assertTransition('REQUESTED', 'COMPLETED');
    }
}
