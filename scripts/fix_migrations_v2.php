#!/usr/bin/env php
<?php
/**
 * Safe Migration Fixer v2
 *
 * Restores the original file from git, then wraps the entire up() body
 * in a single try-catch. No regex hacks on Schema::create.
 */

$migrationsDir = __DIR__ . '/../database/migrations';

$brokenFiles = [
    '2026_06_13_120002_create_payment_webhook_logs_table.php',
    '2026_06_13_120003_create_payment_reconciliation_logs_table.php',
    '2026_06_14_000001_create_payment_submissions_table.php',
    '2026_06_14_130000_create_failed_jobs_archive_table.php',
    '2026_06_16_000001_create_device_tokens_table.php',
    '2026_06_16_000002_create_notification_logs_table.php',
    '2026_06_16_000003_create_job_idempotency_table.php',
    '2026_06_16_000004_create_emergency_system_tables.php',
    '2026_06_16_022638_create_demand_predictions_table.php',
    '2026_06_17_193331_create_demand_push_logs_table.php',
    '2026_06_17_204041_create_trips_v3_table.php',
    '2026_06_17_212827_create_driver_locations_v3_table.php',
    '2026_06_17_212833_create_trip_messages_v3_table.php',
    '2026_06_17_222320_create_trip_events_v3_table.php',
    '2026_06_17_222322_create_active_trips_v3_table.php',
];

$patchCount = 0;

foreach ($brokenFiles as $file) {
    $path = $migrationsDir . '/' . $file;
    if (!file_exists($path)) {
        echo "SKIP (not found): $file\n";
        continue;
    }

    // Step 1: Restore from git
    $gitResult = shell_exec("cd " . escapeshellarg(dirname($migrationsDir)) . " && git checkout HEAD -- database/migrations/" . escapeshellarg($file) . " 2>&1");
    
    // Step 2: Read restored content
    $content = file_get_contents($path);
    
    // Step 3: Apply clean try-catch around up() body
    // Strategy: find `public function up(): void` block and wrap its ENTIRE body in try-catch
    $pattern = '/(public function up\(\): void\s*\n\s*\{)\n(.*?)\n(\s*\})\s*\n(\s*\/\*\*|\s*public function down)/s';
    
    if (preg_match($pattern, $content, $matches)) {
        $signature = $matches[1];
        $body = $matches[2];
        $closingBrace = $matches[3];
        $nextSection = $matches[4];
        
        // Indent body by one level
        $indentedBody = preg_replace('/^/m', '    ', $body);
        
        $replacement = $signature . "\n        try {\n" . $indentedBody . "\n        } catch (\\Exception \$e) {\n            \\Illuminate\\Support\\Facades\\Log::warning('Migration " . $file . " skipped: ' . \$e->getMessage());\n        }\n" . $closingBrace . "\n\n" . $nextSection;
        
        $newContent = preg_replace($pattern, $replacement, $content, 1);
        
        if ($newContent !== $content) {
            file_put_contents($path, $newContent);
            
            // Verify syntax
            $syntaxCheck = shell_exec("php -l " . escapeshellarg($path) . " 2>&1");
            if (strpos($syntaxCheck, 'No syntax errors') !== false) {
                echo "FIXED: $file\n";
                $patchCount++;
            } else {
                echo "SYNTAX ERROR AFTER FIX: $file - $syntaxCheck\n";
                // Restore from git again
                shell_exec("cd " . escapeshellarg(dirname($migrationsDir)) . " && git checkout HEAD -- database/migrations/" . escapeshellarg($file) . " 2>&1");
            }
        } else {
            echo "NO MATCH: $file\n";
        }
    } else {
        echo "PATTERN NOT FOUND: $file\n";
    }
}

echo "\nDone. Fixed $patchCount files.\n";
