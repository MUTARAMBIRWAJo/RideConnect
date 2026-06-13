<?php

namespace App\Listeners\Firebase;

use App\Models\Review;
use App\Services\Firebase\FirebaseSyncService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * DEPRECATED: This listener is being refactored to use FirebaseSyncService
 * 
 * All Firestore writes now go through FirebaseSyncService::syncEvent()
 * This is a transitional listener that will be removed after full migration
 */
class SyncRatingEventsToFirebase
{
    public function __construct(
        private readonly FirebaseSyncService $firebaseSyncService,
    ) {}

    public function handle(object $event): void
    {
        try {
            if (!$this->firebaseSyncService->isEnabled()) {
                return;
            }

            // Handle review creation events
            if (method_exists($event, 'review') && $event->review instanceof Review) {
                $this->handleReviewCreated($event->review);
            }
        } catch (Throwable $e) {
            Log::error('Firebase sync failed for rating event', [
                'event' => get_class($event),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function handleReviewCreated(Review $review): void
    {
        // Use central FirebaseSyncService
        $this->firebaseSyncService->syncEvent('RatingSubmitted', [
            'driver_id' => $review->driver_id,
            'trip_id' => $review->trip_id,
            'passenger_id' => $review->user_id,
            'rating' => $review->rating,
            'review' => $review->comment,
            'categories' => [],
        ]);

        Log::info('Firebase sync: Rating created', ['review_id' => $review->id]);
    }
}
