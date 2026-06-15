<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Kreait\Firebase\Factory;

class FirebaseDebugCommand extends Command
{
    protected $signature = 'firebase:debug';

    protected $description = 'Firebase diagnostic command - trace exact configuration values';

    public function handle(): int
    {
        $this->info('Firebase Diagnostic Command');
        $this->info('===========================');
        $this->newLine();

        // Section 1: ENV VALUES
        $this->info('1. ENV VALUES');
        $this->info('-------------');
        $this->table(['Variable', 'Raw Value', 'Type'], [
            ['FIREBASE_ENABLED', env('FIREBASE_ENABLED'), gettype(env('FIREBASE_ENABLED'))],
            ['FIREBASE_BOOTSTRAP_ENABLED', env('FIREBASE_BOOTSTRAP_ENABLED'), gettype(env('FIREBASE_BOOTSTRAP_ENABLED'))],
            ['FIREBASE_PROJECT_ID', env('FIREBASE_PROJECT_ID'), gettype(env('FIREBASE_PROJECT_ID'))],
            ['FIREBASE_CREDENTIALS_PATH', env('FIREBASE_CREDENTIALS_PATH'), gettype(env('FIREBASE_CREDENTIALS_PATH'))],
            ['FIREBASE_DATABASE_URL', env('FIREBASE_DATABASE_URL'), gettype(env('FIREBASE_DATABASE_URL'))],
            ['FIREBASE_FIRESTORE_DATABASE', env('FIREBASE_FIRESTORE_DATABASE'), gettype(env('FIREBASE_FIRESTORE_DATABASE'))],
        ]);
        $this->newLine();

        // Section 2: CONFIG VALUES
        $this->info('2. CONFIG VALUES');
        $this->info('----------------');
        $this->table(['Config Key', 'Value', 'Type'], [
            ['firebase.enabled', config('firebase.enabled'), gettype(config('firebase.enabled'))],
            ['firebase.bootstrap_enabled', config('firebase.bootstrap_enabled'), gettype(config('firebase.bootstrap_enabled'))],
            ['firebase.project_id', config('firebase.project_id'), gettype(config('firebase.project_id'))],
            ['firebase.credentials', config('firebase.credentials'), gettype(config('firebase.credentials'))],
            ['firebase.database_url', config('firebase.database_url'), gettype(config('firebase.database_url'))],
            ['firebase.firestore_database', config('firebase.firestore_database'), gettype(config('firebase.firestore_database'))],
        ]);
        $this->newLine();

        // Section 3: CREDENTIAL FILE STATUS
        $this->info('3. CREDENTIAL FILE STATUS');
        $this->info('------------------------');
        $credentialsPath = config('firebase.credentials');
        $this->info('Credentials Path: ' . $credentialsPath);
        $this->info('File Exists: ' . (file_exists($credentialsPath) ? 'YES' : 'NO'));
        $this->info('Storage Exists: ' . (Storage::exists($credentialsPath) ? 'YES' : 'NO'));
        $this->info('Is Readable: ' . (is_readable($credentialsPath) ? 'YES' : 'NO'));
        
        if (file_exists($credentialsPath)) {
            $content = file_get_contents($credentialsPath);
            $json = json_decode($content, true);
            $this->info('JSON Valid: ' . ($json !== null ? 'YES' : 'NO'));
            
            if ($json !== null) {
                $this->info('Required Keys:');
                $this->table(['Key', 'Exists'], [
                    ['project_id', isset($json['project_id']) ? 'YES' : 'NO'],
                    ['client_email', isset($json['client_email']) ? 'YES' : 'NO'],
                    ['private_key', isset($json['private_key']) ? 'YES' : 'NO'],
                ]);
                
                if (isset($json['project_id'])) {
                    $this->info('Project ID from credentials: ' . $json['project_id']);
                }
            }
        }
        $this->newLine();

        // Section 4: KREAIT STATUS
        $this->info('4. KREAIT FIREBASE SDK STATUS');
        $this->info('------------------------------');
        $this->info('Factory Class Exists: ' . (class_exists(Factory::class) ? 'YES' : 'NO'));
        $this->info('Firestore Bound: ' . (app()->bound(\Kreait\Firebase\Contract\Firestore::class) ? 'YES' : 'NO'));
        $this->info('Messaging Bound: ' . (app()->bound(\Kreait\Firebase\Contract\Messaging::class) ? 'YES' : 'NO'));
        $this->info('Auth Bound: ' . (app()->bound(\Kreait\Firebase\Contract\Auth::class) ? 'YES' : 'NO'));
        $this->newLine();

        // Section 5: FIRESTORE STATUS
        $this->info('5. FIRESTORE STATUS');
        $this->info('------------------');
        try {
            if (config('firebase.enabled') && file_exists($credentialsPath) && isset($json)) {
                $factory = (new Factory)->withServiceAccount($credentialsPath)
                    ->withFirestoreClientConfig(['credentials' => $json]);
                $firestore = $factory->createFirestore();
                $this->info('Firestore Connection: SUCCESS');
                $this->info('Firestore Instance: ' . get_class($firestore));
            } else {
                $this->warn('Firestore Connection: SKIPPED (Firebase disabled or credentials missing)');
            }
        } catch (\Exception $e) {
            $this->error('Firestore Connection: FAILED');
            $this->error('Error: ' . $e->getMessage());
        }
        $this->newLine();

        // Section 6: MESSAGING STATUS
        $this->info('6. MESSAGING STATUS');
        $this->info('-------------------');
        try {
            if (config('firebase.enabled') && file_exists($credentialsPath)) {
                $factory = (new Factory)->withServiceAccount($credentialsPath);
                $messaging = $factory->createMessaging();
                $this->info('Messaging Connection: SUCCESS');
                $this->info('Messaging Instance: ' . get_class($messaging));
            } else {
                $this->warn('Messaging Connection: SKIPPED (Firebase disabled or credentials missing)');
            }
        } catch (\Exception $e) {
            $this->error('Messaging Connection: FAILED');
            $this->error('Error: ' . $e->getMessage());
        }
        $this->newLine();

        // Section 7: COLLECTION STATUS
        $this->info('7. COLLECTION STATUS');
        $this->info('--------------------');
        try {
            if (config('firebase.enabled') && file_exists($credentialsPath) && isset($json)) {
                $factory = (new Factory)->withServiceAccount($credentialsPath)
                    ->withFirestoreClientConfig(['credentials' => $json]);
                $firestoreWrapper = $factory->createFirestore();
                $firestore = $firestoreWrapper->database();
                
                $collections = ['users', 'drivers', 'active_trips', 'trip_events', 'driver_locations', 'trip_tracking', 'notifications', 'presence', 'device_tokens', 'payments', 'ratings', 'chat_rooms', 'chat_messages'];
                
                $this->info('Checking collections...');
                foreach ($collections as $collection) {
                    try {
                        $doc = $firestore->collection($collection)->limit(1)->documents();
                        $count = iterator_count($doc);
                        $this->info("{$collection}: EXISTS ({$count} documents)");
                    } catch (\Exception $e) {
                        $this->warn("{$collection}: MISSING ({$e->getMessage()})");
                    }
                }
            } else {
                $this->warn('Collection Check: SKIPPED (Firebase disabled or credentials missing)');
            }
        } catch (\Exception $e) {
            $this->error('Collection Check: FAILED');
            $this->error('Error: ' . $e->getMessage());
        }
        $this->newLine();

        // Section 8: SUMMARY
        $this->info('8. DIAGNOSTIC SUMMARY');
        $this->info('---------------------');
        
        $issues = [];
        
        if (!config('firebase.enabled')) {
            $issues[] = '❌ Firebase is disabled in config';
        } else {
            $this->info('✅ Firebase is enabled in config');
        }
        
        if (!file_exists($credentialsPath)) {
            $issues[] = '❌ Credentials file does not exist';
        } else {
            $this->info('✅ Credentials file exists');
        }
        
        if (!class_exists(Factory::class)) {
            $issues[] = '❌ Kreait Factory class does not exist';
        } else {
            $this->info('✅ Kreait Factory class exists');
        }
        
        if (!empty($issues)) {
            $this->newLine();
            $this->error('ISSUES FOUND:');
            foreach ($issues as $issue) {
                $this->error($issue);
            }
            return self::FAILURE;
        } else {
            $this->newLine();
            $this->info('✅ ALL CHECKS PASSED');
            return self::SUCCESS;
        }
    }
}
