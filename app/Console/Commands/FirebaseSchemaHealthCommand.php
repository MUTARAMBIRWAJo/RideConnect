<?php

namespace App\Console\Commands;

use App\Services\Firebase\FirebaseBootstrapService;
use Illuminate\Console\Command;

class FirebaseSchemaHealthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firebase:schema-health {--fix : Attempt to fix missing collections}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate Firestore schema health and check for required collections';

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
        $this->info('Firebase Firestore Schema Health Check');
        $this->info('=======================================');
        $this->newLine();

        // Check if Firebase is enabled
        if (!$this->firebaseBootstrapService->isEnabled()) {
            $this->error('Firebase is not enabled or not configured.');
            $this->warn('Please check your Firebase configuration in .env file.');
            return self::FAILURE;
        }

        $this->info('Validating Firestore schema...');
        $this->newLine();

        $health = $this->firebaseBootstrapService->validateSchemaHealth();

        if ($health['ready']) {
            $this->info('✓ Firestore schema is healthy!');
            $this->newLine();

            $this->info('Collections Ready (' . count($health['collections_ready']) . '/' . $health['total_collections'] . '):');
            foreach ($health['collections_ready'] as $collection) {
                $this->info('  ✓ ' . $collection);
            }

            $this->newLine();
            $this->info('Schema Health Score: 100/100');
            
            return self::SUCCESS;
        } else {
            $this->error('✗ Firestore schema has issues');
            $this->newLine();

            if (!empty($health['collections_ready'])) {
                $this->info('Collections Ready (' . count($health['collections_ready']) . '/' . $health['total_collections'] . '):');
                foreach ($health['collections_ready'] as $collection) {
                    $this->info('  ✓ ' . $collection);
                }
                $this->newLine();
            }

            if (!empty($health['missing'])) {
                $this->warn('Missing Collections (' . count($health['missing']) . '):');
                foreach ($health['missing'] as $collection) {
                    $this->warn('  ✗ ' . $collection);
                }
                $this->newLine();
            }

            if (!empty($health['warnings'])) {
                $this->warn('Warnings:');
                foreach ($health['warnings'] as $warning) {
                    $this->warn('  - ' . $warning);
                }
                $this->newLine();
            }

            $score = (count($health['collections_ready']) / $health['total_collections']) * 100;
            $this->info('Schema Health Score: ' . round($score) . '/100');
            $this->newLine();

            if ($this->option('fix') && !empty($health['missing'])) {
                if ($this->confirm('Do you want to attempt to fix missing collections by running bootstrap?')) {
                    $this->call('firebase:bootstrap', ['--force' => true]);
                    return self::SUCCESS;
                }
            } else {
                $this->info('To fix missing collections, run: php artisan firebase:bootstrap');
            }

            return self::FAILURE;
        }
    }
}
