<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

\App\Models\User::factory()->count(10)->create(['role' => 'PASSENGER']);
\App\Models\Driver::factory()->count(10)->create();
echo "Done\n";
