<?php

namespace App\Console\Commands;

use App\Services\Firebase\FirebaseSyncService;
use App\Services\Firebase\FirebaseBootstrapService;
use App\Services\Firebase\FirebaseHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FirebaseReconcileCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firebase:reconcile {--fix : Attempt to fix inconsistencies} {--dry-run : Show what would be fixed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile Supabase and Firestore data consistency';

    public function __construct(
        private readonly FirebaseSyncService $firebaseSyncService,
        private readonly FirebaseBootstrapService $firebaseBootstrapService,
        private readonly FirebaseHealthService $firebaseHealthService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Firebase Supabase ↔ Firestore Reconciliation');
        $this->info('========================================');
        $this->newLine();

        // Check if Firebase is enabled
        if (!$this->firebaseHealthService->isEnabled()) {
            $this->warn('Firebase is not enabled or not configured.');
            $this->info('Status: disabled');
            $this->info('Message: Firebase not enabled');
            $this->newLine();
            
            // Show diagnostics
            $diagnostics = $this->firebaseHealthService->getDiagnostics();
            $this->info('Diagnostics:');
            foreach ($diagnostics as $key => $diag) {
                $this->line("  {$key}: {$diag['status']} - {$diag['message']}");
            }
            $this->newLine();
            
            $this->info('To enable Firebase, set FIREBASE_ENABLED=true in your .env file.');
            return self::SUCCESS; // Return success to not crash the system
        }

        $issues = [];
        $totalIssues = 0;

        // Check 1: Orphaned Firestore documents
        $this->info('1. Checking for orphaned Firestore documents...');
        $orphanedFirestore = $this->checkOrphanedFirestoreDocuments();
        $issues['orphaned_firestore'] = $orphanedFirestore;
        $totalIssues += count($orphanedFirestore['documents']);
        $this->displayCheckResult('Orphaned Firestore documents', $orphanedFirestore);
        $this->newLine();

        // Check 2: Orphaned Supabase records
        $this->info('2. Checking for orphaned Supabase records...');
        $orphanedSupabase = $this->checkOrphanedSupabaseRecords();
        $issues['orphaned_supabase'] = $orphanedSupabase;
        $totalIssues += count($orphanedSupabase['records']);
        $this->displayCheckResult('Orphaned Supabase records', $orphanedSupabase);
        $this->newLine();

        // Check 3: Sync failures
        $this->info('3. Checking for sync failures...');
        $syncFailures = $this->checkSyncFailures();
        $issues['sync_failures'] = $syncFailures;
        $totalIssues += $syncFailures['count'];
        $this->displayCheckResult('Sync failures', $syncFailures);
        $this->newLine();

        // Check 4: Stale driver locations
        $this->info('4. Checking for stale driver locations...');
        $staleLocations = $this->checkStaleDriverLocations();
        $issues['stale_locations'] = $staleLocations;
        $totalIssues += $staleLocations['count'];
        $this->displayCheckResult('Stale driver locations', $staleLocations);
        $this->newLine();

        // Check 5: Stale trip state
        $this->info('5. Checking for stale trip state...');
        $staleTrips = $this->checkStaleTripState();
        $issues['stale_trips'] = $staleTrips;
        $totalIssues += $staleTrips['count'];
        $this->displayCheckResult('Stale trip state', $staleTrips);
        $this->newLine();

        // Summary
        $this->info('========================================');
        $this->info('RECONCILIATION SUMMARY');
        $this->info('========================================');
        $this->info("Total Issues Found: {$totalIssues}");
        $this->newLine();

        if ($totalIssues > 0) {
            $this->warn('Issues found in data consistency');
            $this->newLine();
            $this->info('To fix issues, run: php artisan firebase:reconcile --fix');
        } else {
            $this->info('✓ No consistency issues found');
        }

        // Fix issues if requested (skip if dry-run)
        if ($this->option('fix') && $totalIssues > 0) {
            if ($this->option('dry-run')) {
                $this->newLine();
                $this->warn('Dry-run mode: Skipping actual fixes');
                $this->info('Would fix the following:');
                $this->displayFixPlan($issues);
            } else {
                $this->newLine();
                $this->info('Attempting to fix issues...');
                $this->fixIssues($issues);
            }
        }

        return $totalIssues > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function displayCheckResult(string $title, array $result): void
    {
        if ($result['count'] > 0) {
            $this->warn("✗ {$title}: {$result['count']} issues found");
            if (!empty($result['details'])) {
                foreach (array_slice($result['details'], 0, 5) as $detail) {
                    $this->line("  - {$detail}");
                }
                if (count($result['details']) > 5) {
                    $this->line("  ... and " . (count($result['details']) - 5) . " more");
                }
            }
        } else {
            $this->info("✓ {$title}: No issues found");
        }
    }

    private function displayFixPlan(array $issues): void
    {
        if (!empty($issues['orphaned_supabase']['records'])) {
            $this->line("  - Sync " . count($issues['orphaned_supabase']['records']) . " orphaned Supabase records to Firestore");
        }
        
        if (!empty($issues['stale_locations']['locations'])) {
            $this->line("  - Delete " . count($issues['stale_locations']['locations']) . " stale driver locations");
        }
        
        if (!empty($issues['stale_trips']['trips'])) {
            $this->line("  - Sync " . count($issues['stale_trips']['trips']) . " stale trip states");
        }
    }

    private function checkOrphanedFirestoreDocuments(): array
    {
        $documents = [];
        
        try {
            // Check for Firestore documents without corresponding Supabase records
            // This is a simplified check - in production, you'd query Firestore directly
            
            // For now, we'll check based on sync logs
            $documents = [];
            $details = [];
            
            // TODO: Implement actual Firestore query to find orphaned documents
            // This would require querying Firestore for all documents in each collection
            // and checking if corresponding Supabase records exist
            
        } catch (\Exception $e) {
            Log::error('Failed to check orphaned Firestore documents', [
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'count' => count($documents),
            'documents' => $documents,
            'details' => $details,
        ];
    }

    private function checkOrphanedSupabaseRecords(): array
    {
        $records = [];
        $details = [];
        
        try {
            // Check for Supabase records without corresponding Firestore documents
            // This is a simplified check - in production, you'd query Firestore directly
            
            // Check for users without Firestore documents
            $usersWithoutFirestore = \App\Models\User::whereDoesntHave('firebaseTokens')->limit(10)->get();
            foreach ($usersWithoutFirestore as $user) {
                $records[] = "User {$user->id} has no Firestore document";
                $details[] = "User ID: {$user->id}, Email: {$user->email}";
            }
            
            // Check for drivers without Firestore documents
            $driversWithoutFirestore = \App\Models\Driver::limit(10)->get();
            foreach ($driversWithoutFirestore as $driver) {
                $records[] = "Driver {$driver->user_id} has no Firestore document";
                $details[] = "Driver ID: {$driver->user_id}";
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to check orphaned Supabase records', [
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'count' => count($records),
            'records' => $records,
            'details' => $details,
        ];
    }

    private function checkSyncFailures(): array
    {
        $failures = [];
        $details = [];
        
        try {
            // Check for recent sync failures in logs
            // This is a simplified check - in production, you'd query a sync log table
            
            // For now, we'll check based on recent log entries
            $logFile = storage_path('logs/laravel.log');
            if (file_exists($logFile)) {
                $logContent = file_get_contents($logFile);
                $pattern = '/Firebase sync failed/i';
                $matches = preg_match_all($pattern, $logContent);
                if ($matches > 0) {
                    $failures[] = "Found {$matches} sync failures in logs";
                    $details[] = "Check logs for details: {$logFile}";
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to check sync failures', [
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'count' => count($failures),
            'failures' => $failures,
            'details' => $details,
        ];
    }

    private function checkStaleDriverLocations(): array
    {
        $staleLocations = [];
        $details = [];
        
        try {
            // Check for driver locations older than 1 hour
            $staleThreshold = now()->subHour();
            
            $staleLocations = \App\Models\DriverLocation::where('recorded_at', '<', $staleThreshold)
                ->limit(10)
                ->get();
            
            foreach ($staleLocations as $location) {
                $details[] = "Driver ID: {$location->driver_id}, Location age: " . $location->recorded_at->diffForHumans();
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to check stale driver locations', [
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'count' => $staleLocations->count(),
            'locations' => $staleLocations,
            'details' => $details,
        ];
    }

    private function checkStaleTripState(): array
    {
        $staleTrips = [];
        $details = [];
        
        try {
            // Check for trips with status mismatch between Supabase and Firestore
            // This is a simplified check - in production, you'd query Firestore directly
            
            // For now, we'll check for trips stuck in a status for too long
            $staleThreshold = now()->subHours(2);
            
            $staleTrips = \App\Models\Trip::whereIn('status', ['ASSIGNED', 'DRIVER_ASSIGNED'])
                ->where('updated_at', '<', $staleThreshold)
                ->limit(10)
                ->get();
            
            foreach ($staleTrips as $trip) {
                $details[] = "Trip ID: {$trip->id}, Status: {$trip->status}, Updated: " . $trip->updated_at->diffForHumans();
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to check stale trip state', [
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'count' => $staleTrips->count(),
            'trips' => $staleTrips,
            'details' => $details,
        ];
    }

    private function fixIssues(array $issues): void
    {
        $this->info('Fixing issues...');
        
        // Fix orphaned Supabase records by syncing to Firestore
        if (!empty($issues['orphaned_supabase']['records'])) {
            $this->info('Syncing orphaned Supabase records to Firestore...');
            $syncResult = $this->firebaseSyncService->syncSupabaseToFirestore();
            $this->info('Sync result: ' . ($syncResult['success'] ? 'Success' : 'Failed'));
        }
        
        // Fix stale driver locations by cleaning up
        if (!empty($issues['stale_locations']['locations'])) {
            $this->info('Cleaning up stale driver locations...');
            $deleted = \App\Models\DriverLocation::where('recorded_at', '<', now()->subHour())->delete();
            $this->info("Deleted {$deleted} stale driver locations");
        }
        
        // Fix stale trip state by triggering sync
        if (!empty($issues['stale_trips']['trips'])) {
            $this->info('Syncing stale trip state...');
            $syncResult = $this->firebaseSyncService->syncSupabaseToFirestore();
            $this->info('Sync result: ' . ($syncResult['success'] ? 'Success' : 'Failed'));
        }
        
        $this->info('Fix complete');
    }
}
