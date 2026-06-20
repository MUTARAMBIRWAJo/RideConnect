<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== KILLING STALE POSTGRES SESSIONS ===" . PHP_EOL;

// Find all sessions holding advisory lock 7426391 or blocking DDL
$locks = DB::select("SELECT pid, state, wait_event_type, wait_event, now() - query_start as duration 
    FROM pg_stat_activity 
    WHERE pid != pg_backend_pid()
    AND (
        state IN ('idle in transaction', 'idle in transaction (aborted)')
        OR EXISTS (
            SELECT 1 FROM pg_locks l WHERE l.pid = pg_stat_activity.pid AND l.locktype = 'advisory'
        )
    )");

echo "Found " . count($locks) . " stale sessions." . PHP_EOL;
foreach ($locks as $lock) {
    echo "  PID: {$lock->pid} | State: {$lock->state} | Duration: {$lock->duration}" . PHP_EOL;
    $result = DB::select("SELECT pg_terminate_backend({$lock->pid}) as killed");
    echo "  Killed: " . ($result[0]->killed ? 'YES' : 'NO') . PHP_EOL;
}

echo PHP_EOL . "=== CHECKING ADVISORY LOCKS AFTER CLEANUP ===" . PHP_EOL;
$remaining = DB::select("SELECT pid, classid, objid, granted FROM pg_locks WHERE locktype = 'advisory'");
echo "Advisory locks remaining: " . count($remaining) . PHP_EOL;
foreach ($remaining as $r) {
    echo "  PID: {$r->pid} | objid: {$r->objid} | granted: " . ($r->granted ? 'yes' : 'no') . PHP_EOL;
}

echo PHP_EOL . "=== CHECKING BLOCKED QUERIES ===" . PHP_EOL;
$blocked = DB::select("SELECT pid, state, wait_event_type, wait_event 
    FROM pg_stat_activity 
    WHERE wait_event_type = 'Lock' AND pid != pg_backend_pid()");
echo "Blocked queries: " . count($blocked) . PHP_EOL;
