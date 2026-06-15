<?php

namespace App\Console\Commands;

use App\Services\Firebase\FirebaseManager;
use App\Services\Firebase\FirestoreManager;
use App\Services\Firebase\RealtimeDatabaseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RideConnectFirebaseInit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rideconnect:firebase:init';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate environment and initialize Firebase schema/nodes';

    /**
     * Execute the console command.
     */
    public function handle(
        FirebaseManager $firebaseManager,
        FirestoreManager $firestoreManager,
        RealtimeDatabaseManager $rtdbManager
    ): int {
        $this->info('Starting Firebase initialization...');
        
        $report = [
            'timestamp' => now()->toIso8601String(),
            'env_validation' => [],
            'rtdb_initialization' => [],
            'firestore_validation' => [],
            'status' => 'success',
        ];

        // 1. Env validation
        $requiredEnv = [
            'FIREBASE_PROJECT_ID',
            'FIREBASE_CREDENTIALS_PATH',
            'FIREBASE_DATABASE_URL',
        ];

        $envValid = true;
        foreach ($requiredEnv as $env) {
            $val = env($env);
            $report['env_validation'][$env] = [
                'exists' => !empty($val),
                'value' => $val ? $this->maskEnv($val) : null,
            ];
            if (empty($val)) {
                $envValid = false;
            }
        }

        if (!$envValid) {
            $report['status'] = 'failed';
            $this->error('Environment variables check failed.');
        } else {
            $this->info('Environment variables check: OK');
        }

        // 2. RTDB verification and node initialization
        $nodes = [
            'drivers_online',
            'driver_locations',
            'active_trips',
            'trip_locations',
            'passenger_requests',
            'system_status',
            'emergency_alerts',
        ];

        if ($envValid) {
            $this->info('Verifying and initializing Realtime Database nodes...');
            foreach ($nodes as $node) {
                try {
                    // Safe write-and-delete test ensuring permissions are correct
                    $rtdbManager->set("{$node}/_init", ['initialized' => true]);
                    $rtdbManager->delete("{$node}/_init");
                    $report['rtdb_initialization'][$node] = 'ok';
                } catch (\Throwable $e) {
                    $report['rtdb_initialization'][$node] = 'failed: ' . $e->getMessage();
                    $report['status'] = 'failed';
                }
            }
        }

        // 3. Firestore connection verification
        if ($envValid) {
            $this->info('Verifying Firestore connection...');
            try {
                // Run short-lived write and delete check
                $firestoreManager->set('system_health_checks', 'ping', [
                    'timestamp' => now()->toIso8601String(),
                ]);
                $firestoreManager->delete('system_health_checks', 'ping');
                $report['firestore_validation']['connection'] = 'ok';
            } catch (\Throwable $e) {
                $report['firestore_validation']['connection'] = 'failed: ' . $e->getMessage();
                $report['status'] = 'failed';
            }
        }

        // 4. Save report
        $reportDir = storage_path('reports');
        if (!file_exists($reportDir)) {
            mkdir($reportDir, 0755, true);
        }
        $reportPath = "{$reportDir}/firebase_verification.json";
        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT));
        $this->info("Verification report saved to: {$reportPath}");

        return $report['status'] === 'success' ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Helper to mask sensitive environment variables.
     */
    protected function maskEnv(string $val): string
    {
        if (strlen($val) <= 8) {
            return '********';
        }
        return substr($val, 0, 4) . '...' . substr($val, -4);
    }
}
