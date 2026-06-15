<?php

namespace App\Services\Firebase;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Contract\Firestore;
use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Contract\Messaging;
use Illuminate\Support\Facades\Log;

class FirebaseManager
{
    protected ?Factory $factory = null;
    protected ?Firestore $firestore = null;
    protected ?Database $database = null;
    protected ?Messaging $messaging = null;

    public function __construct(protected readonly FirebaseHealthService $healthService) {}

    /**
     * Get the configured Firebase factory instance.
     */
    protected function getFactory(): Factory
    {
        if ($this->factory === null) {
            $credentialsPath = config('firebase.credentials');
            $this->factory = (new Factory)->withServiceAccount($credentialsPath);
            
            $projectId = config('firebase.project_id');
            if ($projectId) {
                $this->factory = $this->factory->withProjectId($projectId);
            }
        }
        return $this->factory;
    }

    /**
     * Get the Firestore wrapper client.
     */
    public function firestore(): ?Firestore
    {
        if ($this->firestore === null && $this->healthService->grpcAvailable() && $this->healthService->isEnabled()) {
            try {
                $firestoreDb = config('firebase.firestore_database', '(default)');
                $this->firestore = $this->getFactory()->createFirestore($firestoreDb);
            } catch (\Throwable $e) {
                Log::error('[FirebaseManager] Failed to create Firestore client', ['error' => $e->getMessage()]);
            }
        }
        return $this->firestore;
    }

    /**
     * Get the Realtime Database client.
     */
    public function database(): ?Database
    {
        if ($this->database === null && $this->healthService->isEnabled()) {
            try {
                $databaseUrl = config('firebase.database_url');
                $factory = $this->getFactory();
                if ($databaseUrl) {
                    $factory = $factory->withDatabaseUri($databaseUrl);
                }
                $this->database = $factory->createDatabase();
            } catch (\Throwable $e) {
                Log::error('[FirebaseManager] Failed to create Realtime Database client', ['error' => $e->getMessage()]);
            }
        }
        return $this->database;
    }

    /**
     * Get the Cloud Messaging client.
     */
    public function messaging(): ?Messaging
    {
        if ($this->messaging === null && $this->healthService->isEnabled()) {
            try {
                $this->messaging = $this->getFactory()->createMessaging();
            } catch (\Throwable $e) {
                Log::error('[FirebaseManager] Failed to create Messaging client', ['error' => $e->getMessage()]);
            }
        }
        return $this->messaging;
    }
}
