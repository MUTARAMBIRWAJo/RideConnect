<?php

namespace App\Services\Health\Checks;

use App\Services\Firebase\FirebaseSyncService;
use App\Services\FirebaseRealtimeService;

class FirebaseHealthCheck
{
    public function __construct(
        private readonly FirebaseSyncService $firebaseSyncService,
        private readonly FirebaseRealtimeService $firebaseRealtimeService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function check(bool $extended = false): array
    {
        $timeoutMs = (int) config('health.timeouts.firebase_ms', 3000);

        return \App\Services\HealthCheckService::timed(function () use ($extended) {
            $enabled = (bool) config('firebase.enabled', true);
            $projectId = config('services.firebase.project_id');
            $credentials = config('services.firebase.credentials');
            $databaseUrl = config('services.firebase.database_url');

            if (! $enabled) {
                return [
                    'ok' => true,
                    'status' => 'skipped',
                    'message' => 'Firebase disabled via FIREBASE_ENABLED=false',
                    'details' => [
                        'enabled' => false,
                        'admin_sdk_initialized' => false,
                        'realtime_database_configured' => false,
                    ],
                ];
            }

            $credentialsExist = is_string($credentials) && $credentials !== '' && file_exists($credentials);
            $adminInitialized = $this->firebaseSyncService->isEnabled();

            $details = [
                'enabled' => true,
                'project_id' => $projectId,
                'credentials_present' => $credentialsExist,
                'admin_sdk_initialized' => $adminInitialized,
                'realtime_database_configured' => filled($databaseUrl),
                'firestore_configured' => filled(config('services.firebase.firestore_database')),
                'bootstrap_enabled' => config('firebase.bootstrap_enabled', false),
            ];

            if (! $credentialsExist) {
                return [
                    'ok' => false,
                    'status' => 'error',
                    'message' => 'Firebase credentials file missing',
                    'details' => $details,
                ];
            }

            if (! $adminInitialized) {
                return [
                    'ok' => false,
                    'status' => 'error',
                    'message' => 'Firebase Admin SDK failed to initialize',
                    'details' => $details,
                ];
            }

            if ($extended) {
                $details['connectivity'] = $this->firebaseRealtimeService->connectivityStatus();
                $details['health_check'] = $this->firebaseSyncService->healthCheck();
            }

            return [
                'ok' => true,
                'status' => 'ok',
                'message' => 'Firebase Admin SDK initialized',
                'details' => $details,
            ];
        }, $timeoutMs);
    }
}
