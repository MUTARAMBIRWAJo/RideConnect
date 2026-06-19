<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    $classes = DB::select("SELECT oid, relname, relnamespace, relkind FROM pg_class WHERE relname LIKE '%ride_requests%'");
    echo "pg_class info for 'ride_requests':\n";
    print_r($classes);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}









