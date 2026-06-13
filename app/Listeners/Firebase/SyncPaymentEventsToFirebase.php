<?php

namespace App\Listeners\Firebase;

use App\Events\Domain\PaymentVerified;
use App\Services\Firebase\FirebaseSyncService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * DEPRECATED: This listener is being refactored to use FirebaseSyncService
 * 
 * All Firestore writes now go through FirebaseSyncService::syncEvent()
 * This is a transitional listener that will be removed after full migration
 */
class SyncPaymentEventsToFirebase
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

            if ($event instanceof PaymentVerified) {
                $this->handlePaymentVerified($event);
            }
        } catch (Throwable $e) {
            Log::error('Firebase sync failed for payment event', [
                'event' => get_class($event),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function handlePaymentVerified(PaymentVerified $event): void
    {
        // Use central FirebaseSyncService
        $this->firebaseSyncService->syncEvent('PaymentCompleted', [
            'trip_id' => $event->tripId ?? 0,
            'payment_id' => $event->paymentId,
            'status' => 'completed',
            'verified_at' => now()->toIso8601String(),
        ]);

        Log::info('Firebase sync: Payment verified', ['payment_id' => $event->paymentId]);
    }
}
