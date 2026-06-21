<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo 'DB Time: ' . DB::select('SELECT NOW() as n')[0]->n . PHP_EOL;
echo 'PHP Time (UTC): ' . gmdate('Y-m-d H:i:s') . PHP_EOL;
echo 'PHP Time (Local): ' . date('Y-m-d H:i:s') . PHP_EOL;
