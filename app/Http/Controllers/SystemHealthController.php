<?php

namespace App\Http\Controllers;

use App\Services\Firebase\FCMManager;
use App\Services\Firebase\RealtimeDatabaseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SystemHealthController extends Controller
{
    /**
     * Check the health of Realtime Database, FCM, and Postgres.
     *
     * Architecture: Firestore is permanently disabled (RTDB-only).
     * This endpoint returns the required contract:
     *   { "success": true, "services": { "postgres": "ok", "rtdb": "ok", "fcm": "ok", "firestore": "disabled" } }
     *
     * @param RealtimeDatabaseManager $rtdbManager
     * @param FCMManager $fcmManager
     * @return JsonResponse
     */
    public function firebaseHealth(
        RealtimeDatabaseManager $rtdbManager,
        FCMManager $fcmManager
    ): JsonResponse {
        $services = [
            'postgres'  => 'fail',
            'rtdb'      => 'fail',
            'fcm'       => 'fail',
            'firestore' => 'disabled',  // Firestore permanently disabled — RTDB-only architecture
        ];

        $overallOk = true;

        // 1. PostgreSQL check
        try {
            DB::connection()->getPdo();
            $services['postgres'] = 'ok';
        } catch (\Throwable $e) {
            $overallOk = false;
            $services['postgres'] = 'error: ' . $e->getMessage();
            Log::error('[SystemHealthController] Postgres health check failed', ['error' => $e->getMessage()]);
        }

        // 2. Realtime Database read/write/delete check
        try {
            $rtdbManager->set('system_status/health_ping', ['ping' => true, 'ts' => now()->toIso8601String()]);
            $value = $rtdbManager->get('system_status/health_ping');
            $rtdbManager->delete('system_status/health_ping');

            if (is_array($value) && ($value['ping'] ?? false) === true) {
                $services['rtdb'] = 'ok';
            } else {
                $overallOk = false;
                $services['rtdb'] = 'failed: Read back value did not match';
            }
        } catch (\Throwable $e) {
            $overallOk = false;
            $services['rtdb'] = 'error: ' . $e->getMessage();
            Log::error('[SystemHealthController] RTDB health check failed', ['error' => $e->getMessage()]);
        }

        // 3. FCM topic send test
        try {
            $messageId = $fcmManager->sendToTopic('health_test', 'Health Check', 'Checking messaging channel...', [
                'type' => 'ping',
            ]);
            if ($messageId) {
                $services['fcm'] = 'ok';
            } else {
                $overallOk = false;
                $services['fcm'] = 'failed: No message ID returned';
            }
        } catch (\Throwable $e) {
            $overallOk = false;
            $services['fcm'] = 'error: ' . $e->getMessage();
            Log::error('[SystemHealthController] FCM health check failed', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'success'  => $overallOk,
            'services' => $services,
        ], $overallOk ? 200 : 500);
    }
}
