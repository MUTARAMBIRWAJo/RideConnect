<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use Illuminate\Support\Facades\DB;

$cols = DB::select("SELECT column_name, column_default FROM information_schema.columns WHERE table_name = 'rides' AND column_name IN ('origin_lat','origin_lng','destination_lat','destination_lng','available_seats','price_per_seat','currency','departure_time','arrival_time_estimated','luggage_allowed')");
print_r($cols);
