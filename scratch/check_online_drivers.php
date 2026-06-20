<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$onlineCount = App\Models\Driver::where('is_online', true)->count();
echo "Total online drivers: $onlineCount\n";

$onlineMotorcycles = App\Models\Driver::where('is_online', true)
    ->whereHas('vehicles', function($q) {
        $q->whereIn('vehicle_type', ['motorcycle', 'boda', 'moto', 'motorbike', 'tuk-tuk']);
    })->count();
echo "Online motorcycle drivers: $onlineMotorcycles\n";

// Let's print some online drivers details
$onlineDrivers = App\Models\Driver::with('vehicles')
    ->where('is_online', true)
    ->limit(10)
    ->get()
    ->map(fn($d) => [
        'id' => $d->id,
        'is_online' => $d->is_online,
        'availability_status' => $d->availability_status,
        'status' => $d->status,
        'current_latitude' => $d->current_latitude,
        'current_longitude' => $d->current_longitude,
        'vehicle_types' => $d->vehicles->pluck('vehicle_type')->toArray()
    ]);

print_r($onlineDrivers->toArray());
