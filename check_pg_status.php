<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== ACTIVE POSTGRES SESSIONS ===" . PHP_EOL;
$rows = DB::select("SELECT pid, state, wait_event_type, wait_event, now() - query_start as duration, substring(query,1,80) as q FROM pg_stat_activity WHERE state != 'idle' ORDER BY query_start");
foreach ($rows as $row) {
    echo json_encode($row) . PHP_EOL;
}

echo PHP_EOL . "=== ADVISORY LOCKS ===" . PHP_EOL;
$locks = DB::select("SELECT pid, classid, objid, granted FROM pg_locks WHERE locktype = 'advisory'");
foreach ($locks as $lock) {
    echo json_encode($lock) . PHP_EOL;
}

echo PHP_EOL . "=== PENDING MIGRATIONS COUNT ===" . PHP_EOL;
$pending = DB::select("SELECT COUNT(*) as c FROM information_schema.tables WHERE table_schema = 'public'");
echo "Tables in public schema: " . $pending[0]->c . PHP_EOL;
