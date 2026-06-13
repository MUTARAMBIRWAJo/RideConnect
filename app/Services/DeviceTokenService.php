<?php

namespace App\Services;

use App\Models\MobileDeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging;
use Kreait\Firebase\Exception\MessagingException;

class DeviceTokenService
{
    public function __construct(
        private readonly Messaging $messaging,
    ) {}

    /**
     * Register or update a device token for a user
     */
    public function registerToken(User $user, string $token, string $platform = 'android', string $appVersion = '1.0.0'): MobileDeviceToken
    {
        // Check if token already exists for this user
        $existingToken = MobileDeviceToken::where('token', $token)->first();

        if ($existingToken) {
            // Update existing token
            $existingToken->update([
                'user_id' => $user->id,
                'platform' => $platform,
                'app_version' => $appVersion,
                'active' => true,
                'last_used_at' => now(),
            ]);
            
            return $existingToken->fresh();
        }

        // Create new token
        return MobileDeviceToken::create([
            'user_id' => $user->id,
            'token' => $token,
            'platform' => $platform,
            'app_version' => $appVersion,
            'active' => true,
            'last_used_at' => now(),
        ]);
    }

    /**
     * Remove a device token
     */
    public function removeToken(string $token): bool
    {
        $deleted = MobileDeviceToken::where('token', $token)->delete();

        if ($deleted) {
            try {
                // Unsubscribe from Firebase topic
                $this->messaging->unsubscribeFromTopic($token, 'all-users');
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
     * Get all active tokens for a user
     */
    public function getUserTokens(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return MobileDeviceToken::where('user_id', $userId)
            ->where('active', true)
            ->where('last_used_at', '>', now()->subDays(30))
            ->get();
    }

    /**
     * Validate a token with Firebase
     */
    public function validateToken(string $token): bool
    {
        try {
            $this->messaging->validateRegistrationTokens([$token]);
            return true;
        } catch (MessagingException $e) {
            Log::warning('Invalid FCM token', [
                'token' => substr($token, 0, 20) . '...',
                'error' => $e->getMessage(),
            ]);
            
            // Mark token as inactive
            MobileDeviceToken::where('token', $token)->update(['active' => false]);
            
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
            ->orWhere('active', false)
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
        $tokens = $this->getUserTokens($userId);
        
        if ($tokens->isEmpty()) {
            return;
        }

        try {
            $this->messaging->subscribeToTopic(
                $tokens->pluck('token')->toArray(),
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
        $tokens = $this->getUserTokens($userId);
        
        if ($tokens->isEmpty()) {
            return;
        }

        try {
            $this->messaging->unsubscribeFromTopic(
                $tokens->pluck('token')->toArray(),
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
