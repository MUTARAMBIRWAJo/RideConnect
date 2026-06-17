<?php

namespace App\Jobs;

use App\Models\MotorcycleTrip;
use App\Services\MatchingService;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryTripMatchingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60; // 60 seconds max execution time
    public $retries = 0; // Don't retry the job itself
    public $backoff = 5; // Wait 5 seconds before processing

    public function __construct(private $tripId) {}

    public function handle(
        MatchingService $matchingService,
        NotificationService $notificationService,
        \App\Services\MotorcycleTripService $motorcycleTripService
    ): void
    {
        $trip = MotorcycleTrip::find($this->tripId);

        if (!$trip) {
            Log::error('RetryTripMatchingJob: Trip not found', ['trip_id' => $this->tripId]);
            return;
        }

        // Check if trip is still in matching state
        if ($trip->status !== 'MATCHING_PENDING') {
            Log::info('RetryTripMatchingJob: Trip no longer in MATCHING_PENDING status', [
                'trip_id' => $trip->id,
                'status' => $trip->status,
            ]);
            return;
        }

        // Check if max retries exceeded
        if ($trip->retry_count >= $trip->max_retries) {
            Log::warning('RetryTripMatchingJob: Max retries exceeded', [
                'trip_id' => $trip->id,
                'retry_count' => $trip->retry_count,
                'max_retries' => $trip->max_retries,
            ]);

            $trip->update([
                'status' => 'EXPIRED',
                'matching_status' => 'FAILED_MAX_RETRIES',
            ]);

            // Notify passenger
            $notificationService->sendInAppNotification(
                $trip->passenger_id,
                'TRIP_MATCHING_FAILED',
                'No drivers available',
                'We couldn\'t find a driver for your trip after multiple attempts. Please try again.',
                ['trip_id' => $trip->id]
            );

            Log::info('RetryTripMatchingJob: Trip expired after max retries', ['trip_id' => $trip->id]);
            return;
        }

        // Expand search radius
        $trip->current_search_radius_km = min(
            $trip->current_search_radius_km + 2,
            25 // Max 25 km radius
        );

        // Increment retry count
        $trip->retry_count += 1;
        $trip->last_retry_at = now();
        $trip->matching_status = 'RETRYING';
        $trip->save();

        Log::info('RetryTripMatchingJob: Retrying trip matching', [
            'trip_id' => $trip->id,
            'retry_count' => $trip->retry_count,
            'search_radius_km' => $trip->current_search_radius_km,
        ]);

        // Get excluded driver IDs (drivers who already rejected)
        $excludedDriverIds = $trip->rejected_drivers ? json_decode($trip->rejected_drivers, true) : [];

        // Attempt matching with expanded radius
        $matchResult = $matchingService->matchMotorcycleTrip($trip, $excludedDriverIds, $trip->current_search_radius_km);

        if ($matchResult && !empty($matchResult['driver_id'])) {
            Log::info('RetryTripMatchingJob: Driver found on retry', [
                'trip_id' => $trip->id,
                'driver_id' => $matchResult['driver_id'],
                'retry_count' => $trip->retry_count,
            ]);

            $motorcycleTripService->assignDriver($trip, $matchResult);
            return;
        }

        // No driver found - schedule next retry
        Log::info('RetryTripMatchingJob: No driver found, scheduling next retry', [
            'trip_id' => $trip->id,
            'retry_count' => $trip->retry_count,
        ]);

        $trip->update([
            'matching_status' => 'RETRY_SCHEDULED',
        ]);

        // Schedule next retry with exponential backoff
        $delaySeconds = 15 + (($trip->retry_count - 1) * 5); // 15s, 20s, 25s, 30s, 35s
        dispatch(new self($trip->id))->delay(now()->addSeconds($delaySeconds));

        Log::info('RetryTripMatchingJob: Next retry scheduled', [
            'trip_id' => $trip->id,
            'delay_seconds' => $delaySeconds,
        ]);
    }
}
