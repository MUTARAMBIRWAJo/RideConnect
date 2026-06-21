<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cols = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'jobs'");
foreach ($cols as $col) {
    echo $col->column_name . ': ' . $col->data_type . PHP_EOL;
}
