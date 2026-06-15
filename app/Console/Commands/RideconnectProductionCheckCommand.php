<?php

namespace App\Console\Commands;

use App\Services\Firebase\FirebaseSyncService;
use App\Services\Firebase\FirebaseBootstrapService;
use App\Services\Firebase\FirebaseHealthService;
use App\Services\DeviceTokenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

class RideconnectProductionCheckCommand extends Command
{
    protected $signature = 'rideconnect:production-check';

    protected $description = 'Production readiness check - returns readiness score 0-100';

    public function __construct(
        private readonly FirebaseSyncService $firebaseSyncService,
        private readonly FirebaseBootstrapService $firebaseBootstrapService,
        private readonly FirebaseHealthService $firebaseHealthService,
        private readonly ?DeviceTokenService $deviceTokenService = null,
    ) {
        parent::__construct();
        $this->app = app();
    }

    public function handle(): int
    {
        $this->info('RideConnect Production Readiness Check');
        $this->info('====================================');
        $this->newLine();

        $results = [];
        $totalScore = 0;
        $maxScore = 100;

        // Check 1: Firebase Credentials
        $this->info('1. Firebase Credentials...');
        $credentialsScore = $this->checkFirebaseCredentials();
        $results['firebase_credentials'] = $credentialsScore;
        $totalScore += $credentialsScore['score'];
        $this->displayCheckResult('Firebase Credentials', $credentialsScore);
        $this->newLine();

        // Check 2: Firestore Access
        $this->info('2. Firestore Access...');
        $firestoreScore = $this->checkFirestoreAccess();
        $results['firestore_access'] = $firestoreScore;
        $totalScore += $firestoreScore['score'];
        $this->displayCheckResult('Firestore Access', $firestoreScore);
        $this->newLine();

        // Check 3: FCM Access
        $this->info('3. FCM Access...');
        $fcmScore = $this->checkFCMAccess();
        $results['fcm_access'] = $fcmScore;
        $totalScore += $fcmScore['score'];
        $this->displayCheckResult('FCM Access', $fcmScore);
        $this->newLine();

        // Check 4: Supabase Connection
        $this->info('4. Supabase Connection...');
        $supabaseScore = $this->checkSupabaseConnection();
        $results['supabase_connection'] = $supabaseScore;
        $totalScore += $supabaseScore['score'];
        $this->displayCheckResult('Supabase Connection', $supabaseScore);
        $this->newLine();

        // Check 5: Queue Workers
        $this->info('5. Queue Workers...');
        $queueScore = $this->checkQueueWorkers();
        $results['queue_workers'] = $queueScore;
        $totalScore += $queueScore['score'];
        $this->displayCheckResult('Queue Workers', $queueScore);
        $this->newLine();

        // Check 6: Failed Jobs
        $this->info('6. Failed Jobs...');
        $failedJobsScore = $this->checkFailedJobs();
        $results['failed_jobs'] = $failedJobsScore;
        $totalScore += $failedJobsScore['score'];
        $this->displayCheckResult('Failed Jobs', $failedJobsScore);
        $this->newLine();

        // Check 7: Driver Tracking
        $this->info('7. Driver Tracking...');
        $driverTrackingScore = $this->checkDriverTracking();
        $results['driver_tracking'] = $driverTrackingScore;
        $totalScore += $driverTrackingScore['score'];
        $this->displayCheckResult('Driver Tracking', $driverTrackingScore);
        $this->newLine();

        // Check 8: Payment Verification Flow
        $this->info('8. Payment Verification Flow...');
        $paymentFlowScore = $this->checkPaymentVerificationFlow();
        $results['payment_verification'] = $paymentFlowScore;
        $totalScore += $paymentFlowScore['score'];
        $this->displayCheckResult('Payment Verification Flow', $paymentFlowScore);
        $this->newLine();

        // Check 9: Device Token Sync
        $this->info('9. Device Token Sync...');
        $deviceTokenScore = $this->checkDeviceTokenSync();
        $results['device_token_sync'] = $deviceTokenScore;
        $totalScore += $deviceTokenScore['score'];
        $this->displayCheckResult('Device Token Sync', $deviceTokenScore);
        $this->newLine();

        // Check 10: Firestore Bootstrap
        $this->info('10. Firestore Bootstrap...');
        $bootstrapScore = $this->checkFirestoreBootstrap();
        $results['firestore_bootstrap'] = $bootstrapScore;
        $totalScore += $bootstrapScore['score'];
        $this->displayCheckResult('Firestore Bootstrap', $bootstrapScore);
        $this->newLine();

        // Check 11: Collection Health
        $this->info('11. Collection Health...');
        $collectionHealthScore = $this->checkCollectionHealth();
        $results['collection_health'] = $collectionHealthScore;
        $totalScore += $collectionHealthScore['score'];
        $this->displayCheckResult('Collection Health', $collectionHealthScore);
        $this->newLine();

        // Summary
        $this->info('====================================');
        $this->info('PRODUCTION READINESS SUMMARY');
        $this->info('====================================');
        $this->info("Total Score: {$totalScore}/{$maxScore}");
        $this->info("Readiness: " . $this->getReadinessLevel($totalScore));
        $this->newLine();

        if ($totalScore >= 95) {
            $this->info('✓ PRODUCTION READY');
            return self::SUCCESS;
        } elseif ($totalScore >= 80) {
            $this->warn('⚠ PRODUCTION READY WITH MINOR ISSUES');
            return self::SUCCESS;
        } else {
            $this->error('✗ NOT PRODUCTION READY');
            return self::FAILURE;
        }
    }

    private function checkFirebaseCredentials(): array
    {
        $score = 0;
        $maxScore = 10;
        $issues = [];

        try {
            if (!$this->firebaseHealthService->isEnabled()) {
                $issues[] = 'Firebase not enabled or not configured';
            } else {
                $score += 5;
            }

            if (config('firebase.project_id')) {
                $score += 3;
            } else {
                $issues[] = 'Firebase project ID not configured';
            }

            if ($this->firebaseHealthService->credentialsExist() && $this->firebaseHealthService->credentialsAreValid()) {
                $score += 2;
            } else {
                $issues[] = 'Firebase credentials file not found or invalid';
            }
        } catch (\Exception $e) {
            $issues[] = 'Exception: ' . $e->getMessage();
        }

        return [
            'score' => $score,
            'max_score' => $maxScore,
            'issues' => $issues,
        ];
    }

    private function checkFirestoreAccess(): array
    {
        $score = 0;
        $maxScore = 10;
        $issues = [];

        try {
            if (!$this->firebaseHealthService->isEnabled()) {
                $issues[] = 'Firebase not enabled';
                return ['score' => 0, 'max_score' => $maxScore, 'issues' => $issues];
            }

            if ($this->firebaseHealthService->canConnectFirestore()) {
                $score += 10;
            } else {
                $issues[] = 'Firestore not connected: Check credentials and permissions';
            }
        } catch (\Exception $e) {
            $issues[] = 'Exception: ' . $e->getMessage();
        }

        return [
            'score' => $score,
            'max_score' => $maxScore,
            'issues' => $issues,
        ];
    }

    private function checkFCMAccess(): array
    {
        $score = 0;
        $maxScore = 10;
        $issues = [];

        try {
            // FCM uses Firebase Admin SDK (service account credentials)
            // Check if Firebase is enabled
            if ($this->firebaseHealthService->isEnabled()) {
                $score += 5;
            } else {
                $issues[] = 'Firebase not enabled in configuration';
            }

            // Check if Messaging is available via Admin SDK
            if ($this->firebaseHealthService->canConnectMessaging()) {
                $score += 5;
            } else {
                $issues[] = 'Firebase Admin SDK Messaging not available (check credentials)';
            }
        } catch (\Exception $e) {
            $issues[] = 'Exception: ' . $e->getMessage();
        }

        return [
            'score' => $score,
            'max_score' => $maxScore,
            'issues' => $issues,
        ];
    }

    private function checkSupabaseConnection(): array
    {
        $score = 0;
        $maxScore = 10;
        $issues = [];

        try {
            DB::connection()->getPdo();
            $score += 10;
        } catch (\Exception $e) {
            $issues[] = 'Supabase connection failed: ' . $e->getMessage();
        }

        return [
            'score' => $score,
            'max_score' => $maxScore,
            'issues' => $issues,
        ];
    }

    private function checkQueueWorkers(): array
    {
        $score = 0;
        $maxScore = 10;
        $issues = [];

        try {
            $queueConfig = config('queue.default');
            if ($queueConfig) {
                $score += 5;
            } else {
                $issues[] = 'Queue not configured';
            }

            if (Schema::hasTable('jobs')) {
                $score += 5;
            } else {
                $issues[] = 'Jobs table not found';
            }
        } catch (\Exception $e) {
            $issues[] = 'Exception: ' . $e->getMessage();
        }

        return [
            'score' => $score,
            'max_score' => $maxScore,
            'issues' => $issues,
        ];
    }

    private function checkFailedJobs(): array
    {
        $score = 0;
        $maxScore = 10;
        $issues = [];

        try {
            if (!Schema::hasTable('failed_jobs')) {
                $score += 10;
                return ['score' => $score, 'max_score' => $maxScore, 'issues' => $issues];
            }

            $failedJobCount = DB::table('failed_jobs')->count();
            
            if ($failedJobCount === 0) {
                $score += 10;
            } elseif ($failedJobCount < 10) {
                $score += 5;
                $issues[] = "Found {$failedJobCount} failed jobs";
            } else {
                $issues[] = "Found {$failedJobCount} failed jobs - needs attention";
            }
        } catch (\Exception $e) {
            $issues[] = 'Exception: ' . $e->getMessage();
        }

        return [
            'score' => $score,
            'max_score' => $maxScore,
            'issues' => $issues,
        ];
    }

    private function checkDriverTracking(): array
    {
        $score = 0;
        $maxScore = 10;
        $issues = [];

        try {
            if (method_exists($this->firebaseSyncService, 'syncDriverLocation')) {
                $score += 5;
            } else {
                $issues[] = 'FirebaseSyncService::syncDriverLocation not found';
            }

            if (Schema::hasTable('driver_locations')) {
                $score += 5;
            } else {
                $issues[] = 'Driver locations table not found';
            }
        } catch (\Exception $e) {
            $issues[] = 'Exception: ' . $e->getMessage();
        }

        return [
            'score' => $score,
            'max_score' => $maxScore,
            'issues' => $issues,
        ];
    }

    private function checkPaymentVerificationFlow(): array
    {
        $score = 0;
        $maxScore = 10;
        $issues = [];

        try {
            if (class_exists(\App\Events\Domain\PaymentVerified::class)) {
                $score += 3;
            } else {
                $issues[] = 'PaymentVerified event not found';
            }

            if (class_exists(\App\Listeners\Firebase\UnifiedFirebaseSyncListener::class)) {
                $score += 3;
            } else {
                $issues[] = 'UnifiedFirebaseSyncListener not found';
            }

            if (Schema::hasTable('payment_submissions')) {
                $score += 4;
            } else {
                $issues[] = 'Payment submissions table not found';
            }
        } catch (\Exception $e) {
            $issues[] = 'Exception: ' . $e->getMessage();
        }

        return [
            'score' => $score,
            'max_score' => $maxScore,
            'issues' => $issues,
        ];
    }

    private function checkDeviceTokenSync(): array
    {
        $score = 0;
        $maxScore = 10;
        $issues = [];

        try {
            if (!$this->deviceTokenService) {
                $issues[] = 'DeviceTokenService not available';
                return ['score' => 0, 'max_score' => $maxScore, 'issues' => $issues];
            }

            if (method_exists($this->firebaseSyncService, 'syncDeviceToken')) {
                $score += 5;
            } else {
                $issues[] = 'FirebaseSyncService::syncDeviceToken not found';
            }

            if (Schema::hasTable('mobile_device_tokens')) {
                $score += 5;
            } else {
                $issues[] = 'Mobile device tokens table not found';
            }
        } catch (\Exception $e) {
            $issues[] = 'Exception: ' . $e->getMessage();
        }

        return [
            'score' => $score,
            'max_score' => $maxScore,
            'issues' => $issues,
        ];
    }

    private function checkFirestoreBootstrap(): array
    {
        $score = 0;
        $maxScore = 10;
        $issues = [];

        try {
            if ($this->firebaseHealthService->isBootstrapEnabled()) {
                $score += 5;
            } else {
                $issues[] = 'Firebase bootstrap not enabled';
            }

            if ($this->firebaseHealthService->bootstrapReady()) {
                $score += 5;
            } else {
                $issues[] = 'Firebase bootstrap not ready (check credentials and Firestore connection)';
            }
        } catch (\Exception $e) {
            $issues[] = 'Exception: ' . $e->getMessage();
        }

        return [
            'score' => $score,
            'max_score' => $maxScore,
            'issues' => $issues,
        ];
    }

    private function checkCollectionHealth(): array
    {
        $score = 0;
        $maxScore = 10;
        $issues = [];

        try {
            $health = $this->firebaseBootstrapService->validateSchemaHealth();
            
            if ($health['ready']) {
                $score += 10;
            } else {
                $score += ($health['ready_collections'] / $health['total_collections']) * 10;
                $issues[] = 'Missing collections: ' . implode(', ', $health['missing']);
            }
        } catch (\Exception $e) {
            $issues[] = 'Exception: ' . $e->getMessage();
        }

        return [
            'score' => $score,
            'max_score' => $maxScore,
            'issues' => $issues,
        ];
    }

    private function displayCheckResult(string $title, array $result): void
    {
        $score = $result['score'];
        $maxScore = $result['max_score'];
        $percentage = ($score / $maxScore) * 100;

        if ($percentage >= 90) {
            $this->info("✓ {$title}: {$score}/{$maxScore} ({$percentage}%)");
        } elseif ($percentage >= 70) {
            $this->warn("⚠ {$title}: {$score}/{$maxScore} ({$percentage}%)");
        } else {
            $this->error("✗ {$title}: {$score}/{$maxScore} ({$percentage}%)");
        }

        if (!empty($result['issues'])) {
            foreach ($result['issues'] as $issue) {
                $this->line("  - {$issue}");
            }
        }
    }

    private function getReadinessLevel(int $score): string
    {
        if ($score >= 95) {
            return 'PRODUCTION READY';
        } elseif ($score >= 80) {
            return 'PRODUCTION READY WITH MINOR ISSUES';
        } elseif ($score >= 60) {
            return 'NOT PRODUCTION READY - NEEDS WORK';
        } else {
            return 'NOT PRODUCTION READY - CRITICAL ISSUES';
        }
    }
}
