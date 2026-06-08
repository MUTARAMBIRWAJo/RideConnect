<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Checking saved_locations table...\n";

// Check if table exists
$schema = DB::getSchemaBuilder();
if ($schema->hasTable('saved_locations')) {
    echo "✓ Table exists\n";
    
    // Count records
    $count = DB::table('saved_locations')->count();
    echo "✓ Total records: {$count}\n";
    
    // List all locations
    $locations = DB::table('saved_locations')->get(['name', 'lat', 'lng']);
    echo "\nCurrent locations:\n";
    foreach ($locations as $loc) {
        echo "  - {$loc->name} ({$loc->lat}, {$loc->lng})\n";
    }
    
    // Check if Kigali, Rwanda exists
    $kigali = DB::table('saved_locations')
        ->where('name', 'Kigali, Rwanda')
        ->first();
    
    if ($kigali) {
        echo "\n✓ 'Kigali, Rwanda' already exists\n";
    } else {
        echo "\n✗ 'Kigali, Rwanda' NOT found - adding it now...\n";
        
        // Kigali, Rwanda coordinates (city center)
        DB::table('saved_locations')->insert([
            'name' => 'Kigali, Rwanda',
            'lat' => -1.9536,
            'lng' => 30.0605,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        echo "✓ Added 'Kigali, Rwanda' (-1.9536, 30.0605)\n";
    }
    
    // Also check for Kigali International Airport
    $airport = DB::table('saved_locations')
        ->where('name', 'Kigali International Airport')
        ->first();
    
    if ($airport) {
        echo "✓ 'Kigali International Airport' exists\n";
    } else {
        echo "✗ 'Kigali International Airport' NOT found - adding it now...\n";
        
        DB::table('saved_locations')->insert([
            'name' => 'Kigali International Airport',
            'lat' => -1.9717,
            'lng' => 30.1388,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        echo "✓ Added 'Kigali International Airport' (-1.9717, 30.1388)\n";
    }
    
} else {
    echo "✗ Table does not exist - running migration...\n";
    
    // Run the migration
    \Illuminate\Support\Facades\Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_06_06_000003_create_saved_locations_table.php',
        '--force' => true
    ]);
    
    echo \Illuminate\Support\Facades\Artisan::output();
}

echo "\nDone!\n";
