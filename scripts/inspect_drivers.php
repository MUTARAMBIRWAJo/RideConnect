<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Driver;

echo "Total drivers: ".Driver::count()."\n";
foreach (Driver::all() as $d) {
    echo "#{$d->id} status={$d->status} created_at={$d->created_at} availability_status={$d->availability_status}\n";
}
