<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$drivers = App\Models\Driver::with('vehicles')->get()->map(function($d) {
    return [
        'id' => $d->id,
        'status' => $d->status,
        'is_online' => $d->is_online,
        'availability_status' => $d->availability_status,
        'vehicles' => $d->vehicles->map(function($v) {
            return ['type' => $v->vehicle_type, 'active' => $v->is_active];
        })
    ];
})->toArray();

print_r($drivers);
