<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Update vehicle type of driver 321 to motorcycle
$updated = DB::table('vehicles')->where('driver_id', 321)->update(['vehicle_type' => 'motorcycle']);
echo "Updated vehicles count: $updated\n";

// Confirm driver 321 is online and nearby
$d = App\Models\Driver::with('vehicles')->find(321);
if ($d) {
    echo "Confirmed:\n";
    echo "Is Online: " . ($d->is_online ? 'Yes' : 'No') . "\n";
    echo "Availability Status: " . $d->availability_status . "\n";
    echo "Vehicle Type: " . $d->vehicles->first()->vehicle_type . "\n";
}
