<?php

namespace App\Events\Domain;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverTemporarilyLocked
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $driverId, public string $matchingSessionId)
    {
    }
}
