<?php
$_ENV['APP_ENV'] = 'testing';
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Default DB Connection: " . config('database.default') . "\n";
echo "DB Host: " . config('database.connections.' . config('database.default') . '.host') . "\n";
echo "DB Database: " . config('database.connections.' . config('database.default') . '.database') . "\n";
