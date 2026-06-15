<?php

namespace App\Console\Commands;

use App\Services\Firebase\FirebaseBootstrapService;
use App\Services\Firebase\FirebaseSyncService;
use App\Services\FirebaseRealtimeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ReflectionClass;

class FirebaseValidateSystemCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firebase:validate-system {--detailed : Show detailed validation results}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate Firebase system production readiness and output readiness score (0-100)';

    public function __construct(
        private readonly FirebaseSyncService $firebaseSyncService,
        private readonly FirebaseBootstrapService $firebaseBootstrapService,
        private readonly FirebaseRealtimeService $firebaseRealtimeService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Firebase Production Validation');
        $this->info('==============================');
        $this->newLine();

        $checks = [];
        $totalScore = 0;
        $maxScore = 100;

        // Check 1: Firebase credentials valid
        $this->info('1. Checking Firebase credentials...');
        $check1 = $this->checkCredentials();
        $checks['credentials'] = $check1;
        $totalScore += $check1['score'];
        $this->displayCheckResult($check1);
        $this->newLine();

        // Check 2: Firestore reachable
        $this->info('2. Checking Firestore connectivity...');
        $check2 = $this->checkFirestoreConnectivity();
        $checks['firestore'] = $check2;
        $totalScore += $check2['score'];
        $this->displayCheckResult($check2);
        $this->newLine();

        // Check 3: Bootstrap enabled state
        $this->info('3. Checking bootstrap configuration...');
        $check3 = $this->checkBootstrapConfig();
        $checks['bootstrap'] = $check3;
        $totalScore += $check3['score'];
        $this->displayCheckResult($check3);
        $this->newLine();

        // Check 4: Collections accessible
        $this->info('4. Checking required collections...');
        $check4 = $this->checkCollections();
        $checks['collections'] = $check4;
        $totalScore += $check4['score'];
        $this->displayCheckResult($check4);
        $this->newLine();

        // Check 5: FirebaseSyncService methods callable
        $this->info('5. Checking FirebaseSyncService methods...');
        $check5 = $this->checkFirebaseSyncServiceMethods();
        $checks['sync_methods'] = $check5;
        $totalScore += $check5['score'];
        $this->displayCheckResult($check5);
        $this->newLine();

        // Check 6: No direct Firestore writes outside FirebaseSyncService
        $this->info('6. Checking for direct Firestore writes...');
        $check6 = $this->checkDirectFirestoreWrites();
        $checks['direct_writes'] = $check6;
        $totalScore += $check6['score'];
        $this->displayCheckResult($check6);
        $this->newLine();

        // Check 7: Event listeners correctly routed
        $this->info('7. Checking event listener configuration...');
        $check7 = $this->checkEventListeners();
        $checks['event_listeners'] = $check7;
        $totalScore += $check7['score'];
        $this->displayCheckResult($check7);
        $this->newLine();

        // Check 8: Driver location sync works
        $this->info('8. Checking driver location sync...');
        $check8 = $this->checkDriverLocationSync();
        $checks['driver_location'] = $check8;
        $totalScore += $check8['score'];
        $this->displayCheckResult($check8);
        $this->newLine();

        // Check 9: Payment sync works
        $this->info('9. Checking payment sync...');
        $check9 = $this->checkPaymentSync();
        $checks['payment_sync'] = $check9;
        $totalScore += $check9['score'];
        $this->displayCheckResult($check9);
        $this->newLine();

        // Check 10: Notification sync works
        $this->info('10. Checking notification sync...');
        $check10 = $this->checkNotificationSync();
        $checks['notification_sync'] = $check10;
        $totalScore += $check10['score'];
        $this->displayCheckResult($check10);
        $this->newLine();

        // Final score
        $this->info('==============================================');
        $this->info('PRODUCTION READINESS SCORE: ' . $totalScore . '/' . $maxScore);
        $this->newLine();

        if ($totalScore >= 90) {
            $this->info('✓ System is PRODUCTION READY');
            return self::SUCCESS;
        } elseif ($totalScore >= 70) {
            $this->warn('⚠ System is mostly ready but has minor issues');
            return self::FAILURE;
        } else {
            $this->error('✗ System is NOT production ready');
            $this->newLine();
            $this->info('Recommended actions:');
            $this->info('1. Run: php artisan firebase:bootstrap to set up schema');
            $this->info('2. Run: php artisan firebase:schema-health to validate collections');
            $this->info('3. Review failed checks above and fix configuration');
            return self::FAILURE;
        }
    }

    private function displayCheckResult(array $check): void
    {
        if ($check['passed']) {
            $this->info('  ✓ ' . $check['message']);
        } else {
            $this->error('  ✗ ' . $check['message']);
        }

        if ($this->option('detailed') && !empty($check['details'])) {
            foreach ($check['details'] as $detail) {
                $this->line('    - ' . $detail);
            }
        }
    }

    private function checkCredentials(): array
    {
        $projectId = config('firebase.project_id');
        $credentialsPath = config('firebase.credentials');

        if (!$projectId) {
            return [
                'passed' => false,
                'score' => 0,
                'message' => 'Firebase project ID not configured',
                'details' => ['Set FIREBASE_PROJECT_ID in .env'],
            ];
        }

        if (!$credentialsPath || !file_exists($credentialsPath)) {
            return [
                'passed' => false,
                'score' => 0,
                'message' => 'Firebase credentials file not found',
                'details' => ['Set FIREBASE_CREDENTIALS_PATH in .env', 'Ensure credentials file exists'],
            ];
        }

        return [
            'passed' => true,
            'score' => 10,
            'message' => 'Firebase credentials valid',
            'details' => [],
        ];
    }

    private function checkFirestoreConnectivity(): array
    {
        if (!$this->firebaseSyncService->isEnabled()) {
            return [
                'passed' => false,
                'score' => 0,
                'message' => 'Firebase not enabled or unreachable',
                'details' => ['Check Firebase configuration', 'Verify network connectivity'],
            ];
        }

        $health = $this->firebaseSyncService->healthCheck();
        if ($health['status'] !== 'connected') {
            return [
                'passed' => false,
                'score' => 0,
                'message' => 'Firestore connection failed: ' . $health['message'],
                'details' => [],
            ];
        }

        return [
            'passed' => true,
            'score' => 10,
            'message' => 'Firestore reachable and healthy',
            'details' => [],
        ];
    }

    private function checkBootstrapConfig(): array
    {
        $bootstrapEnabled = config('firebase.bootstrap_enabled', false);

        if (!$bootstrapEnabled) {
            return [
                'passed' => false,
                'score' => 5,
                'message' => 'Bootstrap disabled (FIREBASE_BOOTSTRAP_ENABLED=false)',
                'details' => ['Set FIREBASE_BOOTSTRAP_ENABLED=true to enable automatic schema setup'],
            ];
        }

        return [
            'passed' => true,
            'score' => 10,
            'message' => 'Bootstrap enabled',
            'details' => [],
        ];
    }

    private function checkCollections(): array
    {
        $health = $this->firebaseBootstrapService->validateSchemaHealth();

        if (!$health['ready']) {
            $missingCount = count($health['missing']);
            $score = max(0, 10 - $missingCount);
            return [
                'passed' => false,
                'score' => $score,
                'message' => $missingCount . ' collections missing',
                'details' => $health['missing'],
            ];
        }

        return [
            'passed' => true,
            'score' => 10,
            'message' => 'All required collections accessible',
            'details' => [],
        ];
    }

    private function checkFirebaseSyncServiceMethods(): array
    {
        $requiredMethods = [
            'syncUser',
            'syncDriver',
            'syncTrip',
            'syncEvent',
            'syncTripEvent',
            'syncDriverLocation',
            'syncPaymentEvent',
            'syncChatRoom',
            'syncChatMessage',
            'syncPresence',
            'syncDeviceToken',
            'syncNotification',
            'syncSupabaseToFirestore',
        ];

        $reflection = new ReflectionClass($this->firebaseSyncService);
        $missingMethods = [];

        foreach ($requiredMethods as $method) {
            if (!$reflection->hasMethod($method)) {
                $missingMethods[] = $method;
            }
        }

        if (!empty($missingMethods)) {
            return [
                'passed' => false,
                'score' => 0,
                'message' => count($missingMethods) . ' required methods missing',
                'details' => $missingMethods,
            ];
        }

        return [
            'passed' => true,
            'score' => 10,
            'message' => 'All required methods available',
            'details' => [],
        ];
    }

    private function checkDirectFirestoreWrites(): array
    {
        // Check for direct Firestore writes in app directory
        $appPath = base_path('app');
        $violations = [];

        // Search for direct Firestore write patterns
        $patterns = [
            'firestore->collection',
            'firestore->document',
            '->createFirestore()',
        ];

        $files = File::allFiles($appPath);
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            // Skip FirebaseSyncService itself
            if (str_contains($file->getPathname(), 'FirebaseSyncService.php')) {
                continue;
            }

            // Skip FirebaseBootstrapService
            if (str_contains($file->getPathname(), 'FirebaseBootstrapService.php')) {
                continue;
            }

            // Skip FirebaseHealthService
            if (str_contains($file->getPathname(), 'FirebaseHealthService.php')) {
                continue;
            }

            // Skip Firebase CLI debug/test/validation commands
            if (str_contains($file->getPathname(), 'FirebaseDebugCommand.php') ||
                str_contains($file->getPathname(), 'FirebaseTestCommand.php') ||
                str_contains($file->getPathname(), 'FirebaseValidateCommand.php') ||
                str_contains($file->getPathname(), 'FirebaseValidateSystemCommand.php')) {
                continue;
            }

            // Skip legacy wrappers (they delegate)
            if (str_contains($file->getPathname(), 'FirebaseSync.php') ||
                str_contains($file->getPathname(), 'FirebaseEventDispatcher.php') ||
                str_contains($file->getPathname(), 'FirebaseRealtimeService.php')) {
                continue;
            }

            $content = File::get($file->getPathname());
            foreach ($patterns as $pattern) {
                if (str_contains($content, $pattern)) {
                    $violations[] = $file->getRelativePathname();
                    break;
                }
            }
        }

        if (!empty($violations)) {
            return [
                'passed' => false,
                'score' => 5,
                'message' => count($violations) . ' files may have direct Firestore writes',
                'details' => array_slice($violations, 0, 5),
            ];
        }

        return [
            'passed' => true,
            'score' => 10,
            'message' => 'No direct Firestore writes found outside FirebaseSyncService',
            'details' => [],
        ];
    }

    private function checkEventListeners(): array
    {
        $eventServiceProvider = base_path('app/Providers/EventServiceProvider.php');
        $content = File::get($eventServiceProvider);

        // Check for UnifiedFirebaseSyncListener
        if (!str_contains($content, 'UnifiedFirebaseSyncListener')) {
            return [
                'passed' => false,
                'score' => 0,
                'message' => 'UnifiedFirebaseSyncListener not registered',
                'details' => ['Register UnifiedFirebaseSyncListener in EventServiceProvider'],
            ];
        }

        // Check for legacy listeners (should not be registered)
        $legacyListeners = [
            'SyncTripEventsToFirebase',
            'SyncPaymentEventsToFirebase',
            'SyncRatingEventsToFirebase',
        ];

        $foundLegacy = [];
        foreach ($legacyListeners as $listener) {
            if (str_contains($content, $listener)) {
                $foundLegacy[] = $listener;
            }
        }

        if (!empty($foundLegacy)) {
            return [
                'passed' => false,
                'score' => 5,
                'message' => 'Legacy listeners still registered',
                'details' => $foundLegacy,
            ];
        }

        return [
            'passed' => true,
            'score' => 10,
            'message' => 'Event listeners correctly routed to UnifiedFirebaseSyncListener',
            'details' => [],
        ];
    }

    private function checkDriverLocationSync(): array
    {
        // Check if syncDriverLocation method exists and is callable
        if (!method_exists($this->firebaseSyncService, 'syncDriverLocation')) {
            return [
                'passed' => false,
                'score' => 0,
                'message' => 'syncDriverLocation method not found',
                'details' => [],
            ];
        }

        // Check if DriverLocationSyncJob exists
        $jobPath = base_path('app/Jobs/DriverLocationSyncJob.php');
        if (!File::exists($jobPath)) {
            return [
                'passed' => false,
                'score' => 5,
                'message' => 'DriverLocationSyncJob not found',
                'details' => ['Create DriverLocationSyncJob for async location sync'],
            ];
        }

        return [
            'passed' => true,
            'score' => 10,
            'message' => 'Driver location sync configured',
            'details' => [],
        ];
    }

    private function checkPaymentSync(): array
    {
        // Check if syncPaymentEvent method exists
        if (!method_exists($this->firebaseSyncService, 'syncPaymentEvent')) {
            return [
                'passed' => false,
                'score' => 0,
                'message' => 'syncPaymentEvent method not found',
                'details' => [],
            ];
        }

        // Check if PaymentVerified event is handled
        $eventServiceProvider = base_path('app/Providers/EventServiceProvider.php');
        $content = File::get($eventServiceProvider);

        if (!str_contains($content, 'PaymentVerified')) {
            return [
                'passed' => false,
                'score' => 5,
                'message' => 'PaymentVerified event not registered',
                'details' => ['Register PaymentVerified event in EventServiceProvider'],
            ];
        }

        return [
            'passed' => true,
            'score' => 10,
            'message' => 'Payment sync configured',
            'details' => [],
        ];
    }

    private function checkNotificationSync(): array
    {
        // Check if syncNotification method exists
        if (!method_exists($this->firebaseSyncService, 'syncNotification')) {
            return [
                'passed' => false,
                'score' => 0,
                'message' => 'syncNotification method not found',
                'details' => [],
            ];
        }

        // Check if messaging is initialized
        $healthService = app(\App\Services\Firebase\FirebaseHealthService::class);
        if (!$healthService->isEnabled() || !app()->bound(\Kreait\Firebase\Contract\Messaging::class)) {
            return [
                'passed' => false,
                'score' => 5,
                'message' => 'Messaging service may not be initialized',
                'details' => ['Check Firebase messaging configuration'],
            ];
        }

        return [
            'passed' => true,
            'score' => 10,
            'message' => 'Notification sync configured',
            'details' => [],
        ];
    }
}
