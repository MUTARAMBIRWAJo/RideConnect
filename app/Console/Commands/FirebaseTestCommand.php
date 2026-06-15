<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Contract\Firestore;
use Kreait\Firebase\Contract\Messaging;

class FirebaseTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firebase:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test credentials load, Factory, Firestore connection, and Messaging connection';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Firebase Test Command');
        $this->info('=====================');
        $this->newLine();

        try {
            // 1. Credentials Load
            $this->info('1. Testing Credentials Load...');
            $credentialsPath = config('firebase.credentials');
            if (empty($credentialsPath)) {
                throw new \Exception('FIREBASE_CREDENTIALS_PATH is empty or not configured.');
            }
            $this->line("Path: {$credentialsPath}");
            if (!file_exists($credentialsPath)) {
                throw new \Exception("Credentials file does not exist at: {$credentialsPath}");
            }
            if (!is_readable($credentialsPath)) {
                throw new \Exception("Credentials file is not readable at: {$credentialsPath}");
            }
            $content = file_get_contents($credentialsPath);
            $json = json_decode($content, true);
            if ($json === null) {
                throw new \Exception('Credentials file is not a valid JSON.');
            }
            $requiredKeys = ['project_id', 'client_email', 'private_key'];
            foreach ($requiredKeys as $key) {
                if (!isset($json[$key])) {
                    throw new \Exception("Credentials JSON is missing required key: {$key}");
                }
            }
            $this->info('✓ Credentials load: PASS');
            $this->newLine();

            // 2. Factory creation
            $this->info('2. Testing Factory creation...');
            $factory = (new Factory)
                ->withServiceAccount($credentialsPath)
                ->withFirestoreClientConfig([
                    'credentials' => $json,
                ]);
            
            $projectId = config('firebase.project_id') ?: ($json['project_id'] ?? null);
            if ($projectId) {
                $factory = $factory->withProjectId($projectId);
            }
            $this->info('✓ Factory creation: PASS');
            $this->newLine();

            // 3. Firestore connection (ping)
            $this->info('3. Testing Firestore connection (ping)...');
            $kreaitFirestore = $factory->createFirestore(config('firebase.firestore_database', '(default)'));
            $firestore = $kreaitFirestore->database();
            
            $docRef = $firestore->collection('healthcheck')->document('ping');
            $testData = [
                'timestamp' => now()->toIso8601String(),
                'environment' => config('app.env', 'production'),
                'project_id' => $projectId,
            ];
            
            $docRef->set($testData);
            
            $snapshot = $docRef->snapshot();
            if (!$snapshot->exists()) {
                throw new \Exception('Read back test failed: Document does not exist after write.');
            }
            
            $readData = $snapshot->data();
            if (($readData['project_id'] ?? null) !== $testData['project_id']) {
                throw new \Exception('Read back test failed: Mismatched project_id.');
            }
            
            $docRef->delete();
            $this->info('✓ Firestore connection (ping): PASS');
            $this->newLine();

            // 4. Messaging connection
            $this->info('4. Testing Messaging connection...');
            $messaging = $factory->createMessaging();
            if ($messaging === null) {
                throw new \Exception('Failed to create Messaging client.');
            }
            $this->info('✓ Messaging connection: PASS');
            $this->newLine();

            $this->info('====================================');
            $this->info('PASS');
            $this->info('====================================');
            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('====================================');
            $this->error('FAIL');
            $this->error('====================================');
            $this->error('Error Message: ' . $e->getMessage());
            $this->error('Stack Trace:');
            $this->line($e->getTraceAsString());
            return self::FAILURE;
        }
    }
}
