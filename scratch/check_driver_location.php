<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$d = App\Models\Driver::with('vehicles')->find(321);
if ($d) {
    echo "Driver 321:\n";
    echo "Status: " . $d->status . "\n";
    echo "Is Online: " . ($d->is_online ? 'Yes' : 'No') . "\n";
    echo "Availability Status: " . $d->availability_status . "\n";
    echo "Lat: " . $d->current_latitude . ", Lng: " . $d->current_longitude . "\n";
    
    // Check vehicle type
    foreach ($d->vehicles as $v) {
        echo "Vehicle type: " . $v->vehicle_type . ", Active: " . ($v->is_active ? 'Yes' : 'No') . "\n";
    }
    
    // Calculate distance to Kimironko Market (-1.9441, 30.105)
    $lat1 = -1.9441;
    $lng1 = 30.105;
    $lat2 = (float)$d->current_latitude;
    $lng2 = (float)$d->current_longitude;
    
    $earthRadius = 6371;
    $latDelta = deg2rad($lat2 - $lat1);
    $lngDelta = deg2rad($lng2 - $lng1);
    $a = sin($latDelta / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;
    $distance = $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    echo "Distance to Kimironko: " . round($distance, 2) . " km\n";
} else {
    echo "Driver 321 not found.\n";
}
