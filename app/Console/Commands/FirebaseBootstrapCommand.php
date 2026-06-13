<?php

namespace App\Console\Commands;

use App\Services\Firebase\FirebaseBootstrapService;
use Illuminate\Console\Command;

class FirebaseBootstrapCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firebase:bootstrap {--force : Force bootstrap without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bootstrap Firestore schema with required collections and system documents';

    public function __construct(
        private readonly FirebaseBootstrapService $firebaseBootstrapService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Firebase Firestore Schema Bootstrap');
        $this->info('=====================================');
        $this->newLine();

        // Check if Firebase is enabled
        if (!$this->firebaseBootstrapService->isEnabled()) {
            $this->error('Firebase is not enabled or not configured.');
            $this->warn('Please check your Firebase configuration in .env file.');
            return self::FAILURE;
        }

        // Check if bootstrap is enabled
        if (!$this->firebaseBootstrapService->isBootstrapEnabled()) {
            $this->warn('Firebase bootstrap is disabled (FIREBASE_BOOTSTRAP_ENABLED=false).');
            $this->warn('To enable bootstrap, set FIREBASE_BOOTSTRAP_ENABLED=true in your .env file.');
            
            if (!$this->option('force')) {
                if (!$this->confirm('Do you want to proceed anyway?')) {
                    return self::FAILURE;
                }
            }
        }

        $this->info('Bootstrap will create the following collections:');
        $collections = $this->firebaseBootstrapService->getRequiredCollections();
        $this->table(['Collection'], array_map(fn ($c) => [$c], $collections));
        $this->newLine();

        if (!$this->option('force')) {
            if (!$this->confirm('Do you want to proceed with schema bootstrap?')) {
                $this->warn('Bootstrap cancelled.');
                return self::FAILURE;
            }
        }

        $this->info('Bootstrapping Firestore schema...');
        $this->newLine();

        $result = $this->firebaseBootstrapService->bootstrapSchema();

        if ($result['success']) {
            $this->info('✓ Firestore schema bootstrapped successfully!');
            $this->newLine();

            if (isset($result['results'])) {
                $this->info('Collection Results:');
                $rows = [];
                foreach ($result['results'] as $collection => $status) {
                    if (is_array($status)) {
                        $rows[] = [$collection, $status['status'] ?? 'unknown', $status['error'] ?? ''];
                    } else {
                        $rows[] = [$collection, $status, ''];
                    }
                }
                $this->table(['Collection', 'Status', 'Error'], $rows);
            }

            $this->newLine();
            $this->info('Next steps:');
            $this->info('1. Run: php artisan firebase:schema-health to validate the schema');
            $this->info('2. Run: php artisan firebase:validate-system to check production readiness');
            
            return self::SUCCESS;
        } else {
            $this->error('✗ Schema bootstrap failed: ' . $result['message']);
            return self::FAILURE;
        }
    }
}
