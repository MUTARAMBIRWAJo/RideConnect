<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$toDrop = [
    'driver_behaviors',
    'passenger_behaviors',
    'route_states',
    'weather_conditions'
];

foreach ($toDrop as $table) {
    echo "Dropping table and type for: {$table}\n";
    try {
        DB::statement("DROP TABLE IF EXISTS \"{$table}\" CASCADE");
        DB::statement("DROP TYPE IF EXISTS \"{$table}\" CASCADE");
        DB::statement("DROP TYPE IF EXISTS \"_{$table}\" CASCADE");
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
echo "Done.\n";
