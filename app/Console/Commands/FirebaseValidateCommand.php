<?php

namespace App\Console\Commands;

use App\Services\Firebase\FirebaseSyncService;
use App\Services\Firebase\FirebaseBootstrapService;
use App\Services\DeviceTokenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FirebaseValidateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firebase:validate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate Firebase production readiness and return readiness score';

    public function __construct(
        private readonly FirebaseSyncService $firebaseSyncService,
        private readonly FirebaseBootstrapService $firebaseBootstrapService,
        private ?DeviceTokenService $deviceTokenService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Firebase Production Validation');
        $this->info('===============================');
        $this->newLine();

        $results = [];
        $totalScore = 0;
        $maxScore = 100;

        // Check 1: Firebase Credentials
        $this->info('1. Validating Firebase credentials...');
        $credentialsScore = $this->validateFirebaseCredentials();
        $results['credentials'] = $credentialsScore;
        $totalScore += $credentialsScore['score'];
        $this->displayValidationResult('Firebase Credentials', $credentialsScore);
        $this->newLine();

        // Check 2: Firestore Connectivity
        $this->info('2. Validating Firestore connectivity...');
        $firestoreScore = $this->validateFirestoreConnectivity();
        $results['firestore'] = $firestoreScore;
        $totalScore += $firestoreScore['score'];
        $this->displayValidationResult('Firestore Connectivity', $firestoreScore);
        $this->newLine();

        // Check 3: FCM
        $this->info('3. Validating FCM...');
        $fcmScore = $this->validateFCM();
        $results['fcm'] = $fcmScore;
        $totalScore += $fcmScore['score'];
        $this->displayValidationResult('FCM', $fcmScore);
        $this->newLine();

        // Check 4: Device Tokens
        $this->info('4. Validating Device Tokens...');
        $deviceTokensScore = $this->validateDeviceTokens();
        $results['device_tokens'] = $deviceTokensScore;
        $totalScore += $deviceTokensScore['score'];
        $this->displayValidationResult('Device Tokens', $deviceTokensScore);
        $this->newLine();

        // Check 5: Payment Sync
        $this->info('5. Validating Payment Sync...');
        $paymentSyncScore = $this->validatePaymentSync();
        $results['payment_sync'] = $paymentSyncScore;
        $totalScore += $paymentSyncScore['score'];
        $this->displayValidationResult('Payment Sync', $paymentSyncScore);
        $this->newLine();

        // Check 6: Driver Tracking
        $this->info('6. Validating Driver Tracking...');
        $driverTrackingScore = $this->validateDriverTracking();
        $results['driver_tracking'] = $driverTrackingScore;
        $totalScore += $driverTrackingScore['score'];
        $this->displayValidationResult('Driver Tracking', $driverTrackingScore);
        $this->newLine();

        // Check 7: Trip Tracking
        $this->info('7. Validating Trip Tracking...');
        $tripTrackingScore = $this->validateTripTracking();
        $results['trip_tracking'] = $tripTrackingScore;
        $totalScore += $tripTrackingScore['score'];
        $this->displayValidationResult('Trip Tracking', $tripTrackingScore);
        $this->newLine();

        // Check 8: Presence
        $this->info('8. Validating Presence...');
        $presenceScore = $this->validatePresence();
        $results['presence'] = $presenceScore;
        $totalScore += $presenceScore['score'];
        $this->displayValidationResult('Presence', $presenceScore);
        $this->newLine();

        // Check 9: Notifications
        $this->info('9. Validating Notifications...');
        $notificationsScore = $this->validateNotifications();
        $results['notifications'] = $notificationsScore;
        $totalScore += $notificationsScore['score'];
        $this->displayValidationResult('Notifications', $notificationsScore);
        $this->newLine();

        // Check 10: Collections
        $this->info('10. Validating Firestore Collections...');
        $collectionsScore = $this->validateCollections();
        $results['collections'] = $collectionsScore;
        $totalScore += $collectionsScore['score'];
        $this->displayValidationResult('Collections', $collectionsScore);
        $this->newLine();

        // Summary
        $this->info('===============================');
        $this->info('VALIDATION SUMMARY');
        $this->info('===============================');
        $this->info("Total Score: {$totalScore}/{$maxScore}");
        $this->info("Readiness: " . $this->getReadinessLevel($totalScore));
        $this->newLine();

        if ($totalScore >= 95) {
            $this->info('✓ Production Ready');
            return self::SUCCESS;
        } elseif ($totalScore >= 80) {
            $this->warn('⚠ Production Ready with Minor Issues');
            return self::SUCCESS;
        } else {
            $this->error('✗ Not Production Ready');
            return self::FAILURE;
        }
    }

    private function validateFirebaseCredentials(): array
    {
        $score = 0;
        $maxScore = 10;
        $issues = [];

        try {
            if (!$this->firebaseSyncService->isEnabled()) {
                $issues[] = 'Firebase not enabled or not configured';
            } else {
                $score += 5;
            }

            if (config('firebase.project_id')) {
                $score += 3;
            } else {
                $issues[] = 'Firebase project ID not configured';
            }

            if (config('firebase.credentials') && file_exists(config('firebase.credentials'))) {
                $score += 2;
            } else {
                $issues[] = 'Firebase credentials file not found';
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

    private function validateFirestoreConnectivity(): array
    {
        $score = 0;
        $maxScore = 10;
        $issues = [];

        try {
            if (!$this->firebaseSyncService->isEnabled()) {
                $issues[] = 'Firebase not enabled';
                return ['score' => 0, 'max_score' => $maxScore, 'issues' => $issues];
            }

            $healthCheck = $this->firebaseSyncService->healthCheck();
            
            if ($healthCheck['status'] === 'connected') {
                $score += 10;
            } else {
                $issues[] = 'Firestore not connected: ' . ($healthCheck['message'] ?? 'Unknown error');
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

    private function validateFCM(): array
    {
        $score = 0;
        $maxScore = 10;
        $issues = [];

        try {
            if (config('firebase.fcm.enabled')) {
                $score += 5;
            } else {
                $issues[] = 'FCM not enabled in configuration';
            }

            // Check if FCM server key is configured
            if (config('firebase.fcm.server_key')) {
                $score += 5;
            } else {
                $issues[] = 'FCM server key not configured';
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

    private function validateDeviceTokens(): array
    {
        $score = 0;
        $maxScore = 10;
        $issues = [];

        try {
            if (!$this->deviceTokenService) {
                $issues[] = 'DeviceTokenService not available';
                return ['score' => 0, 'max_score' => $maxScore, 'issues' => $issues];
            }

            $tokenCount = \App\Models\MobileDeviceToken::where('active', true)->count();
            
            if ($tokenCount > 0) {
                $score += 5;
            } else {
                $issues[] = 'No active device tokens found';
            }

            // Check if device token sync is working
            if ($this->firebaseSyncService->isEnabled()) {
                $score += 5;
            } else {
                $issues[] = 'Firebase sync not available for device tokens';
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

    private function validatePaymentSync(): array
    {
        $score = 0;
        $maxScore = 10;
        $issues = [];

        try {
            // Check if PaymentVerified event is registered
            $eventListeners = config('events.listeners', []);
            if (isset($eventListeners[\App\Events\Domain\PaymentVerified::class])) {
                $score += 5;
            } else {
                $issues[] = 'PaymentVerified event not registered';
            }

            // Check if FirebaseSyncService has syncPaymentEvent method
            if (method_exists($this->firebaseSyncService, 'syncPaymentEvent')) {
                $score += 5;
            } else {
                $issues[] = 'FirebaseSyncService::syncPaymentEvent not found';
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

    private function validateDriverTracking(): array
    {
        $score = 0;
        $maxScore = 10;
        $issues = [];

        try {
            // Check if syncDriverLocation method exists
            if (method_exists($this->firebaseSyncService, 'syncDriverLocation')) {
                $score += 5;
            } else {
                $issues[] = 'FirebaseSyncService::syncDriverLocation not found';
            }

            // Check if DriverLocationSyncJob uses FirebaseSyncService
            $jobPath = app_path('Jobs/DriverLocationSyncJob.php');
            if (file_exists($jobPath)) {
                $jobContent = file_get_contents($jobPath);
                if (str_contains($jobContent, 'FirebaseSyncService')) {
                    $score += 5;
                } else {
                    $issues[] = 'DriverLocationSyncJob does not use FirebaseSyncService';
                }
            } else {
                $issues[] = 'DriverLocationSyncJob not found';
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

    private function validateTripTracking(): array
    {
        $score = 0;
        $maxScore = 10;
        $issues = [];

        try {
            // Check if syncTripTracking method exists
            if (method_exists($this->firebaseSyncService, 'syncTripTracking')) {
                $score += 10;
            } else {
                $issues[] = 'FirebaseSyncService::syncTripTracking not found';
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

    private function validatePresence(): array
    {
        $score = 0;
        $maxScore = 10;
        $issues = [];

        try {
            // Check if syncPresence method exists
            if (method_exists($this->firebaseSyncService, 'syncPresence')) {
                $score += 5;
            } else {
                $issues[] = 'FirebaseSyncService::syncPresence not found';
            }

            // Check if MobileDriverController syncs presence
            $controllerPath = app_path('Http/Controllers/Api/MobileDriverController.php');
            if (file_exists($controllerPath)) {
                $controllerContent = file_get_contents($controllerPath);
                if (str_contains($controllerContent, 'syncPresence')) {
                    $score += 5;
                } else {
                    $issues[] = 'MobileDriverController does not sync presence';
                }
            } else {
                $issues[] = 'MobileDriverController not found';
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

    private function validateNotifications(): array
    {
        $score = 0;
        $maxScore = 10;
        $issues = [];

        try {
            // Check if syncNotification method exists
            if (method_exists($this->firebaseSyncService, 'syncNotification')) {
                $score += 5;
            } else {
                $issues[] = 'FirebaseSyncService::syncNotification not found';
            }

            // Check if NotificationDispatcher exists
            $dispatcherPath = app_path('Services/NotificationDispatcher.php');
            if (file_exists($dispatcherPath)) {
                $score += 5;
            } else {
                $issues[] = 'NotificationDispatcher not found';
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

    private function validateCollections(): array
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

    private function displayValidationResult(string $title, array $result): void
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
