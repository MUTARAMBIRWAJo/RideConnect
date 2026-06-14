<?php

namespace App\Services;

use App\Models\MobileDeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging;
use Kreait\Firebase\Exception\MessagingException;

class DeviceTokenService
{
    private ?Messaging $messaging;
    private ?\App\Services\Firebase\FirebaseSyncService $firebaseSyncService;

    public function __construct(
        Messaging $messaging = null,
        \App\Services\Firebase\FirebaseSyncService $firebaseSyncService = null,
    ) {
        $this->messaging = $messaging;
        $this->firebaseSyncService = $firebaseSyncService;
    }

    /**
     * Register or update a device token for a user
     */
    public function registerToken(User $user, string $token, string $platform = 'android', string $appVersion = '1.0.0'): MobileDeviceToken
    {
        // Remove token from other users (prevent duplicates)
        MobileDeviceToken::where('device_token', $token)
            ->where('user_id', '!=', $user->id)
            ->delete();

        // Check if token already exists for this user
        $existingToken = MobileDeviceToken::where('device_token', $token)->first();

        if ($existingToken) {
            // Update existing token
            $existingToken->update([
                'user_id' => $user->id,
                'platform' => $platform,
                'app_version' => $appVersion,
                'is_active' => true,
                'last_used_at' => now(),
            ]);
            
            // Sync to Firestore
            $this->syncToFirestore($user, $token, $platform);
            
            return $existingToken->fresh();
        }

        // Create new token
        $deviceToken = MobileDeviceToken::create([
            'user_id' => $user->id,
            'device_token' => $token,
            'platform' => $platform,
            'app_version' => $appVersion,
            'is_active' => true,
            'last_used_at' => now(),
        ]);

        // Sync to Firestore
        $this->syncToFirestore($user, $token, $platform);

        return $deviceToken;
    }

    /**
     * Sync device token to Firestore
     */
    private function syncToFirestore(User $user, string $token, string $platform): void
    {
        try {
            $this->firebaseSyncService->syncDeviceToken($user->id, $token, $platform);
            Log::info('Device token synced to Firestore', [
                'user_id' => $user->id,
                'platform' => $platform,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to sync device token to Firestore', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remove a device token
     */
    public function removeToken(string $token): bool
    {
        $deleted = MobileDeviceToken::where('device_token', $token)->delete();

        if ($deleted && $this->messaging) {
            try {
                // Unsubscribe from Firebase topic
                $this->messaging->unsubscribeFromTopic($token, 'all-users');
                
                // Remove from Firestore
                $this->removeFromFirestore($token);
            } catch (MessagingException $e) {
                Log::warning('Failed to unsubscribe token from topic', [
                    'token' => substr($token, 0, 20) . '...',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $deleted > 0;
    }

    /**
     * Remove device token from Firestore
     */
    private function removeFromFirestore(string $token): void
    {
        try {
            $this->firebaseSyncService->removeDeviceToken($token);
            Log::info('Device token removed from Firestore', [
                'token' => substr($token, 0, 20) . '...',
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to remove device token from Firestore', [
                'token' => substr($token, 0, 20) . '...',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get all active tokens for a user
     */
    public function getUserTokens(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return MobileDeviceToken::where('user_id', $userId)
            ->where('is_active', true)
            ->where('last_used_at', '>', now()->subDays(30))
            ->get();
    }

    /**
     * Validate a token with Firebase
     */
    public function validateToken(string $token): bool
    {
        if (!$this->messaging) {
            Log::warning('Firebase Messaging not available, skipping token validation');
            return true; // Assume valid if Firebase not available
        }

        try {
            $this->messaging->validateRegistrationTokens([$token]);
            return true;
        } catch (MessagingException $e) {
            Log::warning('Invalid FCM token', [
                'token' => substr($token, 0, 20) . '...',
                'error' => $e->getMessage(),
            ]);
            
            // Mark token as inactive
            MobileDeviceToken::where('device_token', $token)->update(['is_active' => false]);
            
            return false;
        }
    }

    /**
     * Clean up inactive tokens
     */
    public function cleanupInactiveTokens(): int
    {
        $threshold = now()->subDays(90);
        
        $deleted = MobileDeviceToken::where('last_used_at', '<', $threshold)
            ->orWhere('is_active', false)
            ->delete();

        if ($deleted > 0) {
            Log::info("Cleaned up {$deleted} inactive device tokens");
        }

        return $deleted;
    }

    /**
     * Subscribe user to a topic
     */
    public function subscribeToTopic(int $userId, string $topic): void
    {
        if (!$this->messaging) {
            Log::warning('Firebase Messaging not available, skipping topic subscription');
            return;
        }

        $tokens = $this->getUserTokens($userId);
        
        if ($tokens->isEmpty()) {
            return;
        }

        try {
            $this->messaging->subscribeToTopic(
                $tokens->pluck('device_token')->toArray(),
                $topic
            );
            
            Log::info("Subscribed user {$userId} to topic {$topic}", [
                'token_count' => $tokens->count(),
            ]);
        } catch (MessagingException $e) {
            Log::error('Failed to subscribe user to topic', [
                'user_id' => $userId,
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Unsubscribe user from a topic
     */
    public function unsubscribeFromTopic(int $userId, string $topic): void
    {
        if (!$this->messaging) {
            Log::warning('Firebase Messaging not available, skipping topic unsubscription');
            return;
        }

        $tokens = $this->getUserTokens($userId);
        
        if ($tokens->isEmpty()) {
            return;
        }

        try {
            $this->messaging->unsubscribeFromTopic(
                $tokens->pluck('device_token')->toArray(),
                $topic
            );
            
            Log::info("Unsubscribed user {$userId} from topic {$topic}", [
                'token_count' => $tokens->count(),
            ]);
        } catch (MessagingException $e) {
            Log::error('Failed to unsubscribe user from topic', [
                'user_id' => $userId,
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
