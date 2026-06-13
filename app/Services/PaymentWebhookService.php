<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentWebhookLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PaymentWebhookService
{
    public function __construct(
        private readonly LedgerService $ledgerService,
        private readonly WalletService $walletService,
    ) {}

    /**
     * Log incoming webhook request
     */
    public function logWebhook(Request $request, string $provider, ?string $webhookId = null, ?string $eventType = null): PaymentWebhookLog
    {
        return PaymentWebhookLog::create([
            'log_id' => Str::uuid(),
            'payment_provider' => $provider,
            'webhook_id' => $webhookId,
            'event_type' => $eventType,
            'source_ip' => $request->ip(),
            'signature' => $request->header('Stripe-Signature') ?? $request->header('X-Callback-Api-Key'),
            'signature_valid' => false, // Will be updated by verification
            'headers' => $request->headers->all(),
            'payload' => $request->all(),
            'processing_status' => 'received',
            'received_at' => now(),
        ]);
    }

    /**
     * Update webhook log with processing result
     */
    public function updateWebhookLog(PaymentWebhookLog $log, int $statusCode, ?string $responseBody = null, ?string $errorMessage = null): void
    {
        $log->update([
            'http_status_code' => $statusCode,
            'response_body' => $responseBody,
            'error_message' => $errorMessage,
            'processing_status' => $errorMessage ? 'failed' : 'completed',
            'processed_at' => now(),
        ]);
    }

    /**
     * Create payment event record
     */
    public function createPaymentEvent(
        string $provider,
        string $eventType,
        array $payload,
        ?int $paymentId = null,
        ?int $bookingId = null,
        ?int $tripId = null,
        ?int $motorcycleTripId = null
    ): PaymentEvent {
        return PaymentEvent::create([
            'event_id' => Str::uuid(),
            'payment_provider' => $provider,
            'event_type' => $eventType,
            'payment_id' => $paymentId,
            'booking_id' => $bookingId,
            'trip_id' => $tripId,
            'motorcycle_trip_id' => $motorcycleTripId,
            'payload' => $payload,
            'status' => 'pending',
            'retry_count' => 0,
        ]);
    }

    /**
     * Process payment event with retry logic
     */
    public function processPaymentEvent(PaymentEvent $event): bool
    {
        $maxRetries = config('payment.webhook.max_retries', 3);
        
        if ($event->retry_count >= $maxRetries) {
            $event->update([
                'status' => 'failed',
                'error_message' => 'Max retries exceeded',
            ]);
            return false;
        }

        try {
            $event->update(['status' => 'processing', 'retry_count' => $event->retry_count + 1]);

            DB::transaction(function () use ($event) {
                $this->handleEventLogic($event);
                
                $event->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                ]);
            });

            return true;
        } catch (Throwable $e) {
            Log::error('Payment event processing failed', [
                'event_id' => $event->event_id,
                'retry_count' => $event->retry_count,
                'error' => $e->getMessage(),
            ]);

            $event->update([
                'status' => 'pending',
                'error_message' => $e->getMessage(),
            ]);

            // Schedule retry if not at max
            if ($event->retry_count < $maxRetries) {
                $this->scheduleRetry($event);
            }

            return false;
        }
    }

    /**
     * Handle event-specific logic
     */
    private function handleEventLogic(PaymentEvent $event): void
    {
        $payment = null;
        
        // Find associated payment
        if ($event->payment_id) {
            $payment = Payment::find($event->payment_id);
        } elseif ($event->booking_id) {
            $payment = Payment::where('booking_id', $event->booking_id)->first();
        } elseif ($event->trip_id) {
            $payment = Payment::where('trip_id', $event->trip_id)->first();
        } elseif ($event->motorcycle_trip_id) {
            $payment = Payment::where('motorcycle_trip_id', $event->motorcycle_trip_id)->first();
        }

        if (!$payment) {
            throw new \Exception('Payment not found for event');
        }

        match ($event->event_type) {
            'payment_intent.succeeded', 'successful' => $this->handlePaymentSuccess($payment, $event),
            'charge.refunded', 'failed' => $this->handlePaymentFailure($payment, $event),
            default => Log::info('Unhandled event type', ['event_type' => $event->event_type]),
        };
    }

    /**
     * Handle successful payment
     */
    private function handlePaymentSuccess(Payment $payment, PaymentEvent $event): void
    {
        $payment->update([
            'status' => 'COMPLETED',
            'verification_status' => 'verified',
            'paid_at' => now(),
        ]);

        $this->ledgerService->recordPaymentReceived($payment, $event->payment_provider);

        // Credit driver wallet
        $driverId = null;
        if ($payment->trip_id) {
            $driverId = $payment->trip?->driver_id;
        } elseif ($payment->booking_id) {
            $driverId = $payment->booking?->ride?->driver_id;
        } elseif ($payment->motorcycle_trip_id) {
            $driverId = $payment->motorcycleTrip?->driver_id;
        }

        if ($driverId) {
            $this->walletService->creditPending($driverId, (float) $payment->driver_amount);
        }
    }

    /**
     * Handle failed/refunded payment
     */
    private function handlePaymentFailure(Payment $payment, PaymentEvent $event): void
    {
        if ($event->event_type === 'charge.refunded') {
            $payment->update([
                'status' => 'REFUNDED',
                'refunded_at' => now(),
                'verification_status' => 'verified',
            ]);

            $this->ledgerService->recordRefund($payment);

            // Freeze driver funds
            $driverId = null;
            if ($payment->trip_id) {
                $driverId = $payment->trip?->driver_id;
            } elseif ($payment->booking_id) {
                $driverId = $payment->booking?->ride?->driver_id;
            } elseif ($payment->motorcycle_trip_id) {
                $driverId = $payment->motorcycleTrip?->driver_id;
            }

            if ($driverId) {
                try {
                    $this->walletService->freeze($driverId, (float) $payment->driver_amount);
                } catch (Throwable $e) {
                    Log::warning('Could not freeze driver funds for refund', [
                        'driver_id' => $driverId,
                        'payment_id' => $payment->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } else {
            $payment->update([
                'status' => 'FAILED',
                'verification_status' => 'failed',
            ]);
        }
    }

    /**
     * Schedule retry for failed event
     */
    private function scheduleRetry(PaymentEvent $event): void
    {
        $delayMinutes = config('payment.webhook.retry_delay_minutes', 5) * ($event->retry_count + 1);
        
        dispatch(function () use ($event) {
            $this->processPaymentEvent($event);
        })->delay(now()->addMinutes($delayMinutes));
    }

    /**
     * Check for duplicate webhook events
     */
    public function isDuplicateEvent(string $webhookId, string $provider): bool
    {
        return PaymentWebhookLog::where('webhook_id', $webhookId)
            ->where('payment_provider', $provider)
            ->where('processing_status', '!=', 'failed')
            ->exists();
    }

    /**
     * Get failed events for retry
     */
    public function getFailedEvents(int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return PaymentEvent::where('status', 'pending')
            ->where('retry_count', '<', config('payment.webhook.max_retries', 3))
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }
}
