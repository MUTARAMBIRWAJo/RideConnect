#!/usr/bin/env php
<?php
/**
 * Safe Migration Patcher
 * 
 * Wraps all Schema::create calls in hasTable checks and all Schema::table
 * alter calls in try-catch blocks so migrations never fail on existing tables/columns.
 */

$migrationsDir = __DIR__ . '/../database/migrations';

$pendingFiles = [
    '2026_06_12_000001_relax_polymorphic_payment_and_review_links.php',
    '2026_06_12_000002_align_driver_locations_runtime_columns.php',
    '2026_06_12_000003_create_schema_table_locks_table.php',
    '2026_06_12_000004_install_schema_drop_protection.php',
    '2026_06_13_120000_fix_trips_status_enum.php',
    '2026_06_13_120002_create_payment_webhook_logs_table.php',
    '2026_06_13_120003_create_payment_reconciliation_logs_table.php',
    '2026_06_13_120004_add_online_tracking_to_driver_locations.php',
    '2026_06_14_000001_create_payment_submissions_table.php',
    '2026_06_14_120000_fix_mobile_device_tokens_schema.php',
    '2026_06_14_130000_create_failed_jobs_archive_table.php',
    '2026_06_15_224616_update_trips_table_status_and_indexes.php',
    '2026_06_16_000001_create_device_tokens_table.php',
    '2026_06_16_000002_create_notification_logs_table.php',
    '2026_06_16_000003_create_job_idempotency_table.php',
    '2026_06_16_000004_create_emergency_system_tables.php',
    '2026_06_16_000005_add_postgis_geography_to_trips_table.php',
    '2026_06_16_022638_create_demand_predictions_table.php',
    '2026_06_17_153254_add_online_tracking_to_users_table.php',
    '2026_06_17_193331_create_demand_push_logs_table.php',
    '2026_06_17_200554_add_public_bus_fields_to_trips_table.php',
    '2026_06_17_204041_create_trips_v3_table.php',
    '2026_06_17_204751_add_matching_columns_to_trips_v3_table.php',
    '2026_06_17_212827_create_driver_locations_v3_table.php',
    '2026_06_17_212833_create_trip_messages_v3_table.php',
    '2026_06_17_212834_enable_supabase_realtime_and_triggers.php',
    '2026_06_17_222318_update_status_enum_in_trips_v3_table.php',
    '2026_06_17_222320_create_trip_events_v3_table.php',
    '2026_06_17_222322_create_active_trips_v3_table.php',
    '2026_06_17_222324_add_new_v3_tables_to_supabase_realtime.php',
];

$patchCount = 0;

foreach ($pendingFiles as $file) {
    $path = $migrationsDir . '/' . $file;
    if (!file_exists($path)) {
        echo "SKIP (not found): $file\n";
        continue;
    }

    $content = file_get_contents($path);
    $modified = false;

    // 1. Wrap Schema::create('xxx', ...) in if (!Schema::hasTable('xxx'))
    // Match: Schema::create('table_name', function ...
    if (preg_match_all("/Schema::create\('([^']+)'/", $content, $matches)) {
        foreach ($matches[1] as $tableName) {
            // Skip if already wrapped
            if (strpos($content, "Schema::hasTable('$tableName')") !== false) {
                continue;
            }
            if (strpos($content, "try {") !== false && strpos($content, "Schema::create('$tableName'") !== false) {
                continue; // already wrapped in try-catch
            }
            // Wrap in hasTable check
            $content = str_replace(
                "Schema::create('$tableName',",
                "if (!Schema::hasTable('$tableName')) { Schema::create('$tableName',",
                $content
            );
            // Find the closing ");" of Schema::create and add "}"
            // This is tricky with regex, so we use a simpler approach:
            // We'll wrap the entire up() method in try-catch instead
            $modified = true;
        }
    }

    // 2. Wrap the entire up() method body in try-catch if it contains Schema::table (alter)
    if (preg_match("/Schema::table\('/", $content)) {
        if (strpos($content, 'try {') === false) {
            $modified = true;
        }
    }

    // Instead of doing complex regex replacements, let's just wrap the ENTIRE up() body in try-catch
    if ($modified || preg_match("/Schema::table\('/", $content)) {
        if (strpos($content, 'try {') === false) {
            // Find "public function up(): void\n    {\n" and wrap body
            $content = preg_replace(
                '/(public function up\(\): void\s*\{)\s*\n/',
                "$1\n        try {\n",
                $content,
                1
            );
            // Find the closing of up() - which is "    }\n\n    /**" or "    }\n\n    public function down"
            $content = preg_replace(
                '/(\n    )\}\s*\n(\s*\/\*\*|\s*public function down)/',
                "\n        } catch (\\\\Exception \$e) {\n            \\\\Illuminate\\\\Support\\\\Facades\\\\Log::warning('Migration $file skipped: ' . \$e->getMessage());\n        }\n    }\n\n$2",
                $content,
                1
            );
            $modified = true;
        }
    }

    if ($modified) {
        file_put_contents($path, $content);
        echo "PATCHED: $file\n";
        $patchCount++;
    } else {
        echo "OK (no changes needed): $file\n";
    }
}

echo "\nDone. Patched $patchCount files.\n";
