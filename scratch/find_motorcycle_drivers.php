<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$drivers = App\Models\Driver::query()
    ->join('vehicles', 'vehicles.driver_id', '=', 'drivers.id')
    ->where('drivers.status', 'approved')
    ->where('drivers.is_online', true)
    ->whereIn('drivers.availability_status', ['online', 'available'])
    ->whereIn('vehicles.vehicle_type', ['motorcycle', 'boda', 'moto', 'motorbike', 'tuk-tuk'])
    ->get(['drivers.id', 'drivers.current_latitude', 'drivers.current_longitude', 'vehicles.vehicle_type']);

print_r($drivers->toArray());
