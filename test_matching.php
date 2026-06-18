<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$pickupLat = -1.9441;
$pickupLng = 30.0619;
$dropoffLat = -1.9536;
$dropoffLng = 30.0600;

$types = ['motor_vehicle', 'public_bus', 'private_car'];

foreach ($types as $type) {
    $trip = \App\Models\V3\TripV3::create([
        'user_id' => 1,
        'pickup_lat' => $pickupLat,
        'pickup_lng' => $pickupLng,
        'dropoff_lat' => $dropoffLat,
        'dropoff_lng' => $dropoffLng,
        'pickup_location' => 'Kigali City Center',
        'dropoff_location' => 'Kigali Heights',
        'status' => 'REQUESTED',
        'transport_type' => $type
    ]);

    $engine = app(\App\Services\V3\TripMatchingEngineV3::class);
    $lifecycle = app(\App\Services\V3\TripLifecycleEngineV3::class);
    $lifecycle->transition($trip, 'MATCHING');
    $engine->executeMatch($trip);
    
    $trip->refresh();
    dump("Tested $type: Status -> " . $trip->status . " (Driver: " . $trip->matched_driver_id . ")");
}
