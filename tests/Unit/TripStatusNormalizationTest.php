<?php

namespace Tests\Unit;

use App\Models\Trip;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TripStatusNormalizationTest extends TestCase
{
    #[Test]
    public function it_normalizes_lowercase_status_values_to_database_enum_values(): void
    {
        $trip = new Trip();

        $trip->status = 'pending';
        $this->assertSame('PENDING', $trip->status);

        $trip->status = 'completed';
        $this->assertSame('COMPLETED', $trip->status);

        $trip->status = 'in_progress';
        $this->assertSame('STARTED', $trip->status);
    }
}
