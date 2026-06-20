<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== FINAL DATABASE STABILITY REPORT ===" . PHP_EOL;
echo "Generated: " . now() . PHP_EOL . PHP_EOL;

// 1. Table count
$tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename");
$tableCount = count($tables);
echo "1. TABLE COUNT: $tableCount" . PHP_EOL;

// 2. Migration state
$ran = DB::selectOne("SELECT COUNT(*) as c FROM migrations");
$pending = DB::selectOne("SELECT COUNT(*) as c FROM information_schema.tables WHERE table_schema='public'");
echo "2. MIGRATIONS RAN: {$ran->c}" . PHP_EOL;

// 3. Check V3 tables specifically
$v3Tables = ['trips_v3', 'driver_locations_v3', 'trip_messages_v3', 'trip_events_v3', 'active_trips_v3', 'driver_trip_offers'];
echo "3. V3 TABLES:" . PHP_EOL;
foreach ($v3Tables as $t) {
    $exists = Schema::hasTable($t);
    echo "   " . ($exists ? "✅" : "❌") . " $t" . PHP_EOL;
}

// 4. Key seeded data
$userCount = DB::selectOne("SELECT COUNT(*) as c FROM users");
$driverCount = DB::selectOne("SELECT COUNT(*) as c FROM drivers");
$mobileUserCount = DB::selectOne("SELECT COUNT(*) as c FROM mobile_users");
echo "4. SEEDED DATA:" . PHP_EOL;
echo "   Users: {$userCount->c}" . PHP_EOL;
echo "   Drivers: {$driverCount->c}" . PHP_EOL;
echo "   Mobile Users: {$mobileUserCount->c}" . PHP_EOL;

// 5. Check advisory locks
$locks = DB::select("SELECT COUNT(*) as c FROM pg_locks WHERE locktype = 'advisory'");
echo "5. ADVISORY LOCKS: {$locks[0]->c} (should be 0)" . PHP_EOL;

// 6. Online drivers
$online = DB::selectOne("SELECT COUNT(*) as c FROM drivers WHERE is_online = true");
echo "6. ONLINE DRIVERS: {$online->c}" . PHP_EOL;

// 7. Trips V3 row count
$tripsV3 = DB::selectOne("SELECT COUNT(*) as c FROM trips_v3");
echo "7. TRIPS V3 ROWS: {$tripsV3->c}" . PHP_EOL;

echo PHP_EOL . "=== STATUS: " . ($tableCount >= 120 ? "✅ STABLE" : "❌ INCOMPLETE") . " ===" . PHP_EOL;
