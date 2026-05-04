<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use Illuminate\Support\Facades\DB;
$rides = DB::table('rides')->whereNotNull('bus_number')->limit(3)->get();
foreach ($rides as $r) {
    echo "ID: $r->id, bus_number: $r->bus_number, transport_type: $r->transport_type, travel_mode: $r->travel_mode, corridor_id: $r->corridor_id, route_id: $r->route_id\n";
    echo "origin: $r->origin_address, dest: $r->destination_address, origin_lat: $r->origin_lat, dest_lat: $r->destination_lat\n";
}