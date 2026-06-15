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
                    'ok'      => true,
                    'status'  => 'skipped',
                    'message' => 'Firebase disabled via FIREBASE_ENABLED=false',
                    'details' => [
                        'enabled'                       => false,
                        'grpc_available'                => $grpcAvailable,
                        'admin_sdk_initialized'         => false,
                        'realtime_database_configured'  => false,
                        'firestore_available'           => false,
                        'firestore_status'              => 'disabled',
                    ],
                ];
            }

            $credentialsExist = is_string($credentials) && $credentials !== '' && file_exists($credentials);

            $details = [
                'enabled'                      => true,
                'project_id'                   => $projectId,
                'credentials_present'          => $credentialsExist,
                'grpc_available'               => $grpcAvailable,
                'realtime_database_configured' => filled($databaseUrl),
                // Firestore permanently disabled — RTDB-only architecture
                'firestore_configured'         => false,
                'firestore_available'          => false,
                'firestore_status'             => 'disabled',
                'bootstrap_enabled'            => config('firebase.bootstrap_enabled', false),
            ];

            if (! $credentialsExist) {
                return [
                    'ok'      => false,
                    'status'  => 'error',
                    'message' => 'Firebase credentials file missing',
                    'details' => $details,
                ];
            }

            // gRPC not installed is fully OK — Firestore is disabled, we use RTDB only (HTTP/2).
            if (! $grpcAvailable) {
                $details['admin_sdk_initialized'] = true;
                $details['messaging_available']   = true;
                $details['auth_available']         = true;

                return [
                    'ok'      => true,
                    'status'  => 'ok',
                    'message' => 'Firebase RTDB/Messaging available. Firestore disabled (RTDB-only architecture).',
                    'details' => $details,
                ];
            }

            // Full check
            $details['messaging_available'] = true;
            $details['auth_available']       = true;

            if ($extended) {
                $details['admin_sdk_initialized'] = true; // RTDB-only; no Firestore check needed
                $details['connectivity']          = $this->firebaseRealtimeService->connectivityStatus();
            } else {
                $details['admin_sdk_initialized'] = true;
            }

            return [
                'ok'      => true,
                'status'  => 'ok',
                'message' => 'Firebase RTDB and Messaging available. Firestore disabled (RTDB-only architecture).',
                'details' => $details,
            ];
        }, $timeoutMs);
    }
}
