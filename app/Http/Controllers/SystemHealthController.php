<?php

namespace App\Http\Controllers;

use App\Services\Firebase\FCMManager;
use App\Services\Firebase\FirestoreManager;
use App\Services\Firebase\RealtimeDatabaseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SystemHealthController extends Controller
{
    /**
     * Check the health of Firestore, Realtime Database, and FCM connection.
     *
     * @param FirestoreManager $firestoreManager
     * @param RealtimeDatabaseManager $rtdbManager
     * @param FCMManager $fcmManager
     * @return JsonResponse
     */
    public function firebaseHealth(
        FirestoreManager $firestoreManager,
        RealtimeDatabaseManager $rtdbManager,
        FCMManager $fcmManager
    ): JsonResponse {
        $details = [
            'rtdb' => 'fail',
            'firestore' => 'fail',
            'fcm' => 'fail',
        ];

        $status = 'ok';

        // 1. Realtime Database read/write check
        try {
            $rtdbManager->set('system_status/health_ping', ['ping' => true]);
            $value = $rtdbManager->get('system_status/health_ping');
            $rtdbManager->delete('system_status/health_ping');

            if (is_array($value) && ($value['ping'] ?? false) === true) {
                $details['rtdb'] = 'ok';
            } else {
                $status = 'fail';
                $details['rtdb'] = 'failed: Read back value did not match';
            }
        } catch (\Throwable $e) {
            $status = 'fail';
            $details['rtdb'] = 'error: ' . $e->getMessage();
        }

        // 2. Firestore read/write/delete check
        try {
            $firestoreManager->set('system_health_checks', 'health_ping', ['ping' => true]);
            $value = $firestoreManager->get('system_health_checks', 'health_ping');
            $firestoreManager->delete('system_health_checks', 'health_ping');

            if (is_array($value) && ($value['ping'] ?? false) === true) {
                $details['firestore'] = 'ok';
            } else {
                $status = 'fail';
                $details['firestore'] = 'failed: Read back value did not match';
            }
        } catch (\Throwable $e) {
            $status = 'fail';
            $details['firestore'] = 'error: ' . $e->getMessage();
        }

        // 3. FCM topic send test
        try {
            $messageId = $fcmManager->sendToTopic('health_test', 'Health Check', 'Checking messaging channel...', [
                'type' => 'ping',
            ]);
            if ($messageId) {
                $details['fcm'] = 'ok';
            } else {
                $status = 'fail';
                $details['fcm'] = 'failed: No message ID returned';
            }
        } catch (\Throwable $e) {
            $status = 'fail';
            $details['fcm'] = 'error: ' . $e->getMessage();
        }

        return response()->json([
            'status' => $status,
            'details' => $details,
        ], $status === 'ok' ? 200 : 500);
    }
}
