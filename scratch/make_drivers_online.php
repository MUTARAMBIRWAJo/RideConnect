<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$driverIds = [116, 123, 129, 143, 149, 321];

// Update their status, is_online, availability_status and location
foreach ($driverIds as $index => $id) {
    // Generate lat/lng close to Kimironko Market (-1.9441, 30.105)
    $lat = -1.9441 + (rand(-100, 100) / 10000);
    $lng = 30.105 + (rand(-100, 100) / 10000);

    App\Models\Driver::where('id', $id)->update([
        'status' => 'approved',
        'is_online' => true,
        'availability_status' => 'online',
        'current_latitude' => $lat,
        'current_longitude' => $lng,
        'current_trip_id' => null
    ]);

    // Ensure the driver user is approved and online
    $driver = App\Models\Driver::find($id);
    if ($driver && $driver->user_id) {
        App\Models\User::where('id', $driver->user_id)->update([
            'is_approved' => true,
            'is_online' => true
        ]);
    }

    // Ensure they have a vehicle of type motorcycle
    $vehicle = DB::table('vehicles')->where('driver_id', $id)->first();
    if ($vehicle) {
        DB::table('vehicles')->where('driver_id', $id)->update([
            'vehicle_type' => 'motorcycle',
            'is_active' => true
        ]);
    } else {
        DB::table('vehicles')->insert([
            'driver_id' => $id,
            'make' => 'Yamaha',
            'model' => 'Cruiser',
            'year' => 2024,
            'color' => 'Red',
            'vehicle_type' => 'motorcycle',
            'seats' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}

echo "Successfully made " . count($driverIds) . " drivers online and set vehicle type to motorcycle near Kimironko.\n";
