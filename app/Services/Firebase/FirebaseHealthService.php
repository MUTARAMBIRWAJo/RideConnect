<?php

namespace App\Services\Firebase;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Contract\Firestore;
use Kreait\Firebase\Contract\Messaging;

class FirebaseHealthService
{
    private ?Firestore $firestore = null;
    private ?Messaging $messaging = null;
    private array $diagnostics = [];

    /**
     * Check if the native gRPC C extension is loaded.
     * google/cloud-firestore requires ext-grpc for its transport.
     * Without it, Firestore operations are unavailable.
     */
    public function grpcAvailable(): bool
    {
        return extension_loaded('grpc');
    }

    public function isEnabled(): bool
    {
        return config('firebase.enabled', false) === true;
    }

    public function isBootstrapEnabled(): bool
    {
        return config('firebase.bootstrap_enabled', false) === true;
    }

    public function credentialsExist(): bool
    {
        $credentialsPath = config('firebase.credentials');
        if (empty($credentialsPath)) {
            return false;
        }
        return file_exists($credentialsPath) && is_readable($credentialsPath);
    }

    public function credentialsAreValid(): bool
    {
        $credentialsPath = config('firebase.credentials');
        
        if (!$this->credentialsExist()) {
            return false;
        }

        $content = file_get_contents($credentialsPath);
        $json = json_decode($content, true);
        
        if ($json === null) {
            return false;
        }

        $requiredKeys = ['project_id', 'client_email', 'private_key'];
        foreach ($requiredKeys as $key) {
            if (!isset($json[$key])) {
                return false;
            }
        }

        return true;
    }

    public function canConnectFirestore(): bool
    {
        if (!$this->isEnabled() || !$this->credentialsAreValid()) {
            return false;
        }

        if (!$this->grpcAvailable()) {
            Log::warning('[FirebaseHealthService] ext-grpc not installed — Firestore connectivity check skipped');
            return false;
        }

        try {
            $firestoreWrapper = $this->getFirestore();
            if ($firestoreWrapper === null) {
                return false;
            }
            $firestore = $firestoreWrapper->database();

            // Real connectivity check: Write, Read, Delete under healthcheck/ping
            $docRef = $firestore->collection('healthcheck')->document('ping');
            $testData = [
                'timestamp' => now()->toIso8601String(),
                'environment' => config('app.env'),
                'project_id' => config('firebase.project_id'),
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
            return true;
        } catch (\Exception $e) {
            Log::error('[FirebaseHealthService] Firestore connection/ping failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function canConnectMessaging(): bool
    {
        if (!$this->isEnabled() || !$this->credentialsAreValid()) {
            return false;
        }

        try {
            $this->messaging = $this->getMessaging();
            return $this->messaging !== null;
        } catch (\Exception $e) {
            Log::error('[FirebaseHealthService] Messaging connection failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function bootstrapReady(): bool
    {
        return $this->isEnabled() && 
               $this->isBootstrapEnabled() && 
               $this->credentialsAreValid() && 
               $this->canConnectFirestore();
    }

    public function checkCollectionHealth(string $collection): array
    {
        $status = [
            'exists' => false,
            'read' => false,
            'write' => false,
            'delete' => false,
            'errors' => [],
        ];

        if (!$this->isEnabled() || !$this->credentialsAreValid()) {
            $status['errors'][] = 'Firebase not enabled or credentials invalid';
            return $status;
        }

        try {
            $firestoreWrapper = $this->getFirestore();
            if (!$firestoreWrapper) {
                $status['errors'][] = 'Firestore client not initialized';
                return $status;
            }
            $firestore = $firestoreWrapper->database();

            // 1. Check if the bootstrap schema metadata document exists to confirm existence
            $bootstrapDoc = $firestore->collection($collection)
                ->document('_schema_seed')
                ->collection('bootstrap')
                ->document('metadata');

            try {
                $snapshot = $bootstrapDoc->snapshot();
                $status['exists'] = $snapshot->exists();
                $status['read'] = true;
            } catch (\Exception $e) {
                $status['errors'][] = 'Read seed failed: ' . $e->getMessage();
            }

            // 2. Perform real write and delete check to verify access
            $tempDoc = $firestore->collection($collection)->document('_health_check_temp');
            try {
                $tempDoc->set([
                    'test' => true,
                    'timestamp' => now()->toIso8601String(),
                ]);
                $status['write'] = true;

                $tempSnapshot = $tempDoc->snapshot();
                if ($tempSnapshot->exists()) {
                    $status['read'] = true;
                }

                $tempDoc->delete();
                $status['delete'] = true;
            } catch (\Exception $e) {
                $status['errors'][] = 'Write/Delete check failed: ' . $e->getMessage();
            }

        } catch (\Exception $e) {
            $status['errors'][] = 'Unexpected error: ' . $e->getMessage();
        }

        return $status;
    }

    public function getDiagnostics(): array
    {
        $this->diagnostics = [
            'enabled' => [
                'status' => $this->isEnabled() ? 'pass' : 'fail',
                'value' => $this->isEnabled(),
                'message' => $this->isEnabled() ? 'Firebase is enabled' : 'Firebase is disabled',
            ],
            'bootstrap_enabled' => [
                'status' => $this->isBootstrapEnabled() ? 'pass' : 'fail',
                'value' => $this->isBootstrapEnabled(),
                'message' => $this->isBootstrapEnabled() ? 'Bootstrap is enabled' : 'Bootstrap is disabled',
            ],
            'credentials_exist' => [
                'status' => $this->credentialsExist() ? 'pass' : 'fail',
                'value' => $this->credentialsExist(),
                'message' => $this->credentialsExist() ? 'Credentials file exists' : 'Credentials file missing',
            ],
            'credentials_valid' => [
                'status' => $this->credentialsAreValid() ? 'pass' : 'fail',
                'value' => $this->credentialsAreValid(),
                'message' => $this->credentialsAreValid() ? 'Credentials are valid' : 'Credentials are invalid',
            ],
            'firestore_connection' => [
                'status' => $this->canConnectFirestore() ? 'pass' : 'fail',
                'value' => $this->canConnectFirestore(),
                'message' => $this->canConnectFirestore() ? 'Firestore connection successful' : 'Firestore connection failed',
            ],
            'messaging_connection' => [
                'status' => $this->canConnectMessaging() ? 'pass' : 'fail',
                'value' => $this->canConnectMessaging(),
                'message' => $this->canConnectMessaging() ? 'Messaging connection successful' : 'Messaging connection failed',
            ],
            'bootstrap_ready' => [
                'status' => $this->bootstrapReady() ? 'pass' : 'fail',
                'value' => $this->bootstrapReady(),
                'message' => $this->bootstrapReady() ? 'Bootstrap is ready' : 'Bootstrap is not ready',
            ],
        ];

        return $this->diagnostics;
    }

    public function getFirestore(): ?Firestore
    {
        if (!$this->grpcAvailable()) {
            Log::warning('gRPC extension not installed. Firestore operations are unavailable.');
            return null;
        }

        if ($this->firestore === null) {
            if (app()->bound(Firestore::class)) {
                $this->firestore = app(Firestore::class);
            } else {
                $this->firestore = $this->createFirestore();
            }
        }
        return $this->firestore;
    }

    public function getMessaging(): ?Messaging
    {
        if ($this->messaging === null) {
            if (app()->bound(Messaging::class)) {
                $this->messaging = app(Messaging::class);
            } else {
                $this->messaging = $this->createMessaging();
            }
        }
        return $this->messaging;
    }

    private function createFirestore(): ?Firestore
    {
        try {
            $credentialsPath = config('firebase.credentials');
            $factory = (new Factory)->withServiceAccount($credentialsPath);
            
            if (file_exists($credentialsPath)) {
                $credentials = json_decode(file_get_contents($credentialsPath), true);
                if ($credentials !== null) {
                    $factory = $factory->withFirestoreClientConfig([
                        'credentials' => $credentials,
                    ]);
                }
            }

            $firestoreDb = config('firebase.firestore_database', '(default)');
            return $factory->createFirestore($firestoreDb);
        } catch (\Exception $e) {
            Log::error('[FirebaseHealthService] Failed to create Firestore', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function createMessaging(): ?Messaging
    {
        try {
            $credentialsPath = config('firebase.credentials');
            $factory = (new Factory)->withServiceAccount($credentialsPath);
            return $factory->createMessaging();
        } catch (\Exception $e) {
            Log::error('[FirebaseHealthService] Failed to create Messaging', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
