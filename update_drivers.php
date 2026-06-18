<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

\App\Models\Driver::query()->update(['last_seen_at' => now(), 'is_online' => true, 'status' => 'approved', 'is_available' => true]);
