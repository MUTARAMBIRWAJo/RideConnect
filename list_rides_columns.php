<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use Illuminate\Support\Facades\DB;
$cols = DB::select("SELECT column_name, is_nullable, data_type, character_maximum_length FROM information_schema.columns WHERE table_name = 'rides' ORDER BY ordinal_position");
foreach ($cols as $c) {
    echo sprintf("%-30s %-10s %-20s\n", $c->column_name, $c->is_nullable, $c->data_type);
}