<?php

namespace App\Jobs;

use App\Services\PaymentWebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryFailedPaymentEventsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        private readonly int $limit = 100,
    ) {}

    public function handle(PaymentWebhookService $webhookService): void
    {
        $events = $webhookService->getFailedEvents($this->limit);

        Log::info('Retrying failed payment events', ['count' => $events->count()]);

        $successCount = 0;
        $failureCount = 0;

        foreach ($events as $event) {
            try {
                $result = $webhookService->processPaymentEvent($event);
                if ($result) {
                    $successCount++;
                } else {
                    $failureCount++;
                }
            } catch (\Throwable $e) {
                Log::error('Failed to retry payment event', [
                    'event_id' => $event->event_id,
                    'error' => $e->getMessage(),
                ]);
                $failureCount++;
            }
        }

        Log::info('Payment event retry completed', [
            'total' => $events->count(),
            'success' => $successCount,
            'failure' => $failureCount,
        ]);
    }
}
