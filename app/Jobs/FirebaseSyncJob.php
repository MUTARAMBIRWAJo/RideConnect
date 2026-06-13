<?php

namespace App\Jobs;

use App\Services\FirebaseSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FirebaseSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(
        public readonly string $action,
        public readonly array $data = [],
    ) {}

    public function handle(FirebaseSync $firebaseSync): void
    {
        try {
            if (!$firebaseSync->isEnabled()) {
                Log::debug('Firebase sync disabled, skipping job');
                return;
            }

            match ($this->action) {
                'sync_user_creation' => $firebaseSync->syncUserCreation($this->data['user']),
                'sync_user_profile_update' => $firebaseSync->syncUserProfileUpdate($this->data['user']),
                'sync_driver_profile_creation' => $firebaseSync->syncDriverProfileCreation($this->data['driver']),
                'sync_driver_status' => $firebaseSync->syncDriverStatus($this->data['driver_id'], $this->data['status']),
                'sync_driver_location' => $firebaseSync->syncDriverLocation(
                    $this->data['driver_id'],
                    $this->data['latitude'],
                    $this->data['longitude'],
                    $this->data['accuracy'] ?? 0
                ),
                'sync_trip_creation' => $firebaseSync->syncTripCreation($this->data['trip']),
                'sync_trip_status_update' => $firebaseSync->syncTripStatusUpdate($this->data['trip_id'], $this->data['status']),
                'sync_trip_completion' => $firebaseSync->syncTripCompletion($this->data['trip_id'], $this->data['payment_data']),
                'sync_rating_creation' => $firebaseSync->syncRatingCreation($this->data['driver_id'], $this->data['rating_data']),
                'batch_sync' => $firebaseSync->batchSync($this->data['operations']),
                default => Log::warning('Unknown Firebase sync action', ['action' => $this->action]),
            };

            Log::debug('Firebase sync job completed', ['action' => $this->action]);
        } catch (\Throwable $e) {
            Log::error('Firebase sync job failed', [
                'action' => $this->action,
                'error' => $e->getMessage(),
            ]);
            
            // Retry with exponential backoff
            $this->release($this->attempts() * 10);
        }
    }
}
