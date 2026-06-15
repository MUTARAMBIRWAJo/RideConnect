<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverOnline
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $driverId,
        public readonly string $status = 'online_available'
    ) {}
}
