<?php

namespace App\Jobs\V3;

use App\Models\V3\TripV3;
use App\Services\V3\TripMatchingEngineV3;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessTripMatchingV3 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public TripV3 $trip;

    public function __construct(TripV3 $trip)
    {
        $this->trip = $trip;
    }

    public function handle(TripMatchingEngineV3 $matchingEngine): void
    {
        $matchingEngine->executeMatch($this->trip);
    }
}
