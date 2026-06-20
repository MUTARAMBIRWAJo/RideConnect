<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== EXISTING TABLES IN PUBLIC SCHEMA ===" . PHP_EOL;
$tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename");
$existingTables = array_map(fn($t) => $t->tablename, $tables);
echo "Count: " . count($existingTables) . PHP_EOL;
foreach ($existingTables as $t) {
    echo "  - $t" . PHP_EOL;
}

echo PHP_EOL . "=== MIGRATIONS ALREADY IN migrations TABLE ===" . PHP_EOL;
$ran = DB::select("SELECT migration FROM migrations ORDER BY migration");
$ranNames = array_map(fn($r) => $r->migration, $ran);
echo "Count: " . count($ranNames) . PHP_EOL;

echo PHP_EOL . "=== CHECKING transport_corridors specifically ===" . PHP_EOL;
$exists = Schema::hasTable('transport_corridors');
echo "transport_corridors exists: " . ($exists ? 'YES' : 'NO') . PHP_EOL;

// Check the migration that failed
$migName = '2026_05_18_000009_create_public_bus_transport_tables';
$alreadyRan = in_array($migName, $ranNames);
echo "Migration $migName already recorded: " . ($alreadyRan ? 'YES' : 'NO') . PHP_EOL;

if ($exists && !$alreadyRan) {
    echo PHP_EOL . "==> Marking $migName as run..." . PHP_EOL;
    $batch = DB::selectOne("SELECT COALESCE(MAX(batch), 0) + 1 as next_batch FROM migrations")->next_batch;
    DB::table('migrations')->insert(['migration' => $migName, 'batch' => $batch]);
    echo "Done." . PHP_EOL;
}
