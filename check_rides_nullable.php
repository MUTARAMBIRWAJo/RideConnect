<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use Illuminate\Support\Facades\DB;

$cols = DB::select("SELECT column_name, is_nullable FROM information_schema.columns WHERE table_name = 'rides' AND column_name IN ('origin_lat','origin_lng','destination_lat','destination_lng')");
print_r($cols);
