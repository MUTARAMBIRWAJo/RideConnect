<?php

namespace App\Domain\Matching;

use App\Models\Ride;
use Illuminate\Support\Collection;

interface DriverMatchingStrategy
{
    public function findBestDriver(Ride $ride, Collection $drivers): ?array;
}
