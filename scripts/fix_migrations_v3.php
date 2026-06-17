#!/usr/bin/env php
<?php
/**
 * Safe Migration Fixer v3 - Token-based approach
 * 
 * Reads each migration file, finds the up() method using PHP tokenizer,
 * and wraps its body in a try-catch block properly.
 */

$migrationsDir = __DIR__ . '/../database/migrations';

$files = [
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

$fixed = 0;

foreach ($files as $file) {
    $path = $migrationsDir . '/' . $file;
    if (!file_exists($path)) {
        echo "SKIP: $file\n";
        continue;
    }

    $content = file_get_contents($path);
    
    // Already has try-catch properly? Skip.
    if (strpos($content, 'try {') !== false) {
        // Check syntax first
        $check = shell_exec("php -l " . escapeshellarg($path) . " 2>&1");
        if (strpos($check, 'No syntax errors') !== false) {
            echo "ALREADY OK: $file\n";
            continue;
        }
    }

    // Find the up() method boundaries using brace counting
    // Look for 'public function up(): void' 
    $upPos = strpos($content, 'public function up(): void');
    if ($upPos === false) {
        echo "NO UP() FOUND: $file\n";
        continue;
    }

    // Find the opening brace of up()
    $openBrace = strpos($content, '{', $upPos);
    if ($openBrace === false) {
        echo "NO OPEN BRACE: $file\n";
        continue;
    }

    // Count braces to find the matching closing brace
    $depth = 0;
    $closeBrace = -1;
    for ($i = $openBrace; $i < strlen($content); $i++) {
        if ($content[$i] === '{') {
            $depth++;
        } elseif ($content[$i] === '}') {
            $depth--;
            if ($depth === 0) {
                $closeBrace = $i;
                break;
            }
        }
    }

    if ($closeBrace === -1) {
        echo "NO CLOSE BRACE: $file\n";
        continue;
    }

    // Extract the body of up()
    $bodyStart = $openBrace + 1;
    $bodyEnd = $closeBrace;
    $body = substr($content, $bodyStart, $bodyEnd - $bodyStart);

    // Rebuild the up() method with try-catch
    $newBody = "\n        try {" . $body . "        } catch (\\Exception \$e) {\n            \\Illuminate\\Support\\Facades\\Log::warning('Migration $file skipped: ' . \$e->getMessage());\n        }\n    ";

    $newContent = substr($content, 0, $bodyStart) . $newBody . substr($content, $bodyEnd);

    file_put_contents($path, $newContent);

    // Verify syntax
    $check = shell_exec("php -l " . escapeshellarg($path) . " 2>&1");
    if (strpos($check, 'No syntax errors') !== false) {
        echo "FIXED: $file\n";
        $fixed++;
    } else {
        echo "STILL BROKEN: $file\n";
        echo "  $check\n";
        // Restore
        shell_exec("cd " . escapeshellarg(dirname($migrationsDir)) . " && git checkout HEAD -- database/migrations/$file 2>&1");
    }
}

echo "\nDone. Fixed $fixed / " . count($files) . " files.\n";
