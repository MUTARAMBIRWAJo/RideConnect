<?php

namespace App\Jobs;

use App\Services\Firebase\FirebaseSyncService;
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
    public int $backoff = 10; // seconds between retries

    public function __construct(
        public readonly string $action,
        public readonly array $data = [],
    ) {}

    public function handle(FirebaseSyncService $firebaseSyncService): void
    {
        $jobId = $this->job?->getJobId() ?? 'unknown';
        
        Log::info('[FirebaseSyncJob] Starting', [
            'job_id' => $jobId,
            'action' => $this->action,
            'attempt' => $this->attempts(),
        ]);

        try {
            if (!$firebaseSyncService->isEnabled()) {
                Log::warning('[FirebaseSyncJob] Firebase sync disabled, skipping job', [
                    'job_id' => $jobId,
                    'action' => $this->action,
                ]);
                return;
            }

            $result = match ($this->action) {
                'sync_user_creation' => $this->handleUserCreation($firebaseSyncService),
                'sync_user_profile_update' => $this->handleUserProfileUpdate($firebaseSyncService),
                'sync_driver_profile_creation' => $this->handleDriverProfileCreation($firebaseSyncService),
                'sync_driver_status' => $this->handleDriverStatus($firebaseSyncService),
                'sync_driver_location' => $this->handleDriverLocation($firebaseSyncService),
                'sync_trip_creation' => $this->handleTripCreation($firebaseSyncService),
                'sync_trip_status_update' => $this->handleTripStatusUpdate($firebaseSyncService),
                'sync_trip_completion' => $this->handleTripCompletion($firebaseSyncService),
                'sync_rating_creation' => $this->handleRatingCreation($firebaseSyncService),
                'batch_sync' => $this->handleBatchSync($firebaseSyncService),
                default => $this->handleUnknownAction(),
            };

            if ($result) {
                Log::info('[FirebaseSyncJob] Completed successfully', [
                    'job_id' => $jobId,
                    'action' => $this->action,
                ]);
            } else {
                Log::warning('[FirebaseSyncJob] Completed with warning', [
                    'job_id' => $jobId,
                    'action' => $this->action,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('[FirebaseSyncJob] Failed', [
                'job_id' => $jobId,
                'action' => $this->action,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Dead-letter logging after max retries
            if ($this->attempts() >= $this->tries) {
                Log::critical('[FirebaseSyncJob] Dead-letter - max retries exceeded', [
                    'job_id' => $jobId,
                    'action' => $this->action,
                    'data' => $this->data,
                    'error' => $e->getMessage(),
                ]);
            }

            // Retry with exponential backoff
            throw $e;
        }
    }

    private function handleUserCreation(FirebaseSyncService $firebaseSyncService): bool
    {
        if (!isset($this->data['user'])) {
            Log::warning('[FirebaseSyncJob] Missing user data for sync_user_creation');
            return false;
        }

        return $firebaseSyncService->syncEvent('UserCreated', [
            'user_id' => $this->data['user']->id,
        ]);
    }

    private function handleUserProfileUpdate(FirebaseSyncService $firebaseSyncService): bool
    {
        if (!isset($this->data['user'])) {
            Log::warning('[FirebaseSyncJob] Missing user data for sync_user_profile_update');
            return false;
        }

        return $firebaseSyncService->syncEvent('UserUpdated', [
            'user_id' => $this->data['user']->id,
            'email' => $this->data['user']->email,
            'name' => $this->data['user']->name,
            'phone' => $this->data['user']->phone,
        ]);
    }

    private function handleDriverProfileCreation(FirebaseSyncService $firebaseSyncService): bool
    {
        if (!isset($this->data['driver'])) {
            Log::warning('[FirebaseSyncJob] Missing driver data for sync_driver_profile_creation');
            return false;
        }

        return $firebaseSyncService->syncEvent('DriverCreated', [
            'driver_id' => $this->data['driver']->user_id,
        ]);
    }

    private function handleDriverStatus(FirebaseSyncService $firebaseSyncService): bool
    {
        if (!isset($this->data['driver_id'], $this->data['status'])) {
            Log::warning('[FirebaseSyncJob] Missing required data for sync_driver_status');
            return false;
        }

        return $firebaseSyncService->syncEvent('DriverStatusUpdated', [
            'driver_id' => $this->data['driver_id'],
            'status' => $this->data['status'],
        ]);
    }

    private function handleDriverLocation(FirebaseSyncService $firebaseSyncService): bool
    {
        if (!isset($this->data['driver_id'], $this->data['latitude'], $this->data['longitude'])) {
            Log::warning('[FirebaseSyncJob] Missing required data for sync_driver_location');
            return false;
        }

        return $firebaseSyncService->syncDriverLocation(
            $this->data['driver_id'],
            $this->data['latitude'],
            $this->data['longitude'],
            $this->data['accuracy'] ?? 0,
            $this->data['trip_id'] ?? null
        );
    }

    private function handleTripCreation(FirebaseSyncService $firebaseSyncService): bool
    {
        if (!isset($this->data['trip'])) {
            Log::warning('[FirebaseSyncJob] Missing trip data for sync_trip_creation');
            return false;
        }

        return $firebaseSyncService->syncEvent('TripCreated', [
            'trip_id' => $this->data['trip']->id,
        ]);
    }

    private function handleTripStatusUpdate(FirebaseSyncService $firebaseSyncService): bool
    {
        if (!isset($this->data['trip_id'], $this->data['status'])) {
            Log::warning('[FirebaseSyncJob] Missing required data for sync_trip_status_update');
            return false;
        }

        $eventMap = [
            'accepted' => 'DriverAssigned',
            'driver_arriving' => 'DriverAssigned',
            'arrived' => 'DriverAssigned',
            'in_progress' => 'TripStarted',
            'completed' => 'TripCompleted',
            'cancelled' => 'TripCancelled',
        ];

        $eventType = $eventMap[$this->data['status']] ?? 'TripStatusUpdated';
        
        return $firebaseSyncService->syncEvent($eventType, [
            'trip_id' => $this->data['trip_id'],
            'status' => $this->data['status'],
        ]);
    }

    private function handleTripCompletion(FirebaseSyncService $firebaseSyncService): bool
    {
        if (!isset($this->data['trip_id'])) {
            Log::warning('[FirebaseSyncJob] Missing trip_id for sync_trip_completion');
            return false;
        }

        return $firebaseSyncService->syncEvent('TripCompleted', [
            'trip_id' => $this->data['trip_id'],
            'payment_data' => $this->data['payment_data'] ?? [],
        ]);
    }

    private function handleRatingCreation(FirebaseSyncService $firebaseSyncService): bool
    {
        if (!isset($this->data['driver_id'], $this->data['rating_data'])) {
            Log::warning('[FirebaseSyncJob] Missing required data for sync_rating_creation');
            return false;
        }

        return $firebaseSyncService->syncEvent('RatingSubmitted', [
            'driver_id' => $this->data['driver_id'],
            'rating_data' => $this->data['rating_data'],
        ]);
    }

    private function handleBatchSync(FirebaseSyncService $firebaseSyncService): bool
    {
        if (!isset($this->data['operations'])) {
            Log::warning('[FirebaseSyncJob] Missing operations for batch_sync');
            return false;
        }

        // Convert batch operations to individual syncEvent calls
        $successCount = 0;
        foreach ($this->data['operations'] as $operation) {
            if ($firebaseSyncService->syncEvent('BatchOperation', $operation)) {
                $successCount++;
            }
        }

        Log::info('[FirebaseSyncJob] Batch sync completed', [
            'total' => count($this->data['operations']),
            'successful' => $successCount,
        ]);

        return $successCount === count($this->data['operations']);
    }

    private function handleUnknownAction(): bool
    {
        Log::warning('[FirebaseSyncJob] Unknown action', [
            'action' => $this->action,
        ]);
        return false;
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical('[FirebaseSyncJob] Job failed permanently', [
            'action' => $this->action,
            'data' => $this->data,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}
