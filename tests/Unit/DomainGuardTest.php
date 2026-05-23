<?php

namespace Tests\Unit;

use App\Domain\Core\DomainGuard;
use App\Exceptions\DomainException;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\TripController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DomainGuardTest extends TestCase
{
    #[Test]
    public function it_detects_policy_bypass_patterns(): void
    {
        $this->expectException(DomainException::class);

        (new class
        {
            public function run(): void
            {
                $ride = (object) ['travel_mode' => 'SCHEDULED'];
                if ($ride->travel_mode === 'SCHEDULED') {
                    DomainGuard::assertUsingPolicy(__METHOD__);
                }
            }
        })->run();
    }

    #[Test]
    public function it_allows_guarded_controllers(): void
    {
        DomainGuard::assertControllerUsesPolicies(BookingController::class);
        DomainGuard::assertControllerUsesPolicies(TripController::class);

        $this->assertTrue(true);
    }
}
