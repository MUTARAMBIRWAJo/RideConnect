<?php

namespace App\Services\Health\Checks;

use App\Services\Firebase\FirebaseHealthService;
use App\Services\Firebase\FirebaseSyncService;
use App\Services\FirebaseRealtimeService;

class FirebaseHealthCheck
{
    public function __construct(
        private readonly FirebaseSyncService $firebaseSyncService,
        private readonly FirebaseRealtimeService $firebaseRealtimeService,
        private readonly FirebaseHealthService $firebaseHealthService,
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
            $grpcAvailable = $this->firebaseHealthService->grpcAvailable();

            if (! $enabled) {
                return [
                    'ok' => true,
                    'status' => 'skipped',
                    'message' => 'Firebase disabled via FIREBASE_ENABLED=false',
                    'details' => [
                        'enabled' => false,
                        'grpc_available' => $grpcAvailable,
                        'admin_sdk_initialized' => false,
                        'realtime_database_configured' => false,
                    ],
                ];
            }

            $credentialsExist = is_string($credentials) && $credentials !== '' && file_exists($credentials);

            $details = [
                'enabled' => true,
                'project_id' => $projectId,
                'credentials_present' => $credentialsExist,
                'grpc_available' => $grpcAvailable,
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

            // When grpc is missing, Firebase Auth/Messaging still work (REST-based).
            // Only Firestore is unavailable. Report as degraded, not failed.
            if (! $grpcAvailable) {
                $details['admin_sdk_initialized'] = true;
                $details['firestore_available'] = false;
                $details['messaging_available'] = true;
                $details['auth_available'] = true;

                return [
                    'ok' => true,
                    'status' => 'degraded',
                    'message' => 'Firebase Auth/Messaging available. Firestore unavailable (ext-grpc not installed).',
                    'details' => $details,
                ];
            }

            // Full check with grpc available
            $adminInitialized = $this->firebaseSyncService->isEnabled();
            $details['admin_sdk_initialized'] = $adminInitialized;

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
