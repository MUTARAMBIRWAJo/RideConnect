<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\LedgerService;
use App\Services\WalletService;
use App\Services\PaymentWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledgerService,
        private readonly WalletService $walletService,
        private readonly PaymentWebhookService $webhookService,
    ) {}

    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature', '');
        $secret = config('services.stripe.webhook_secret', '');

        $event = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response('Invalid JSON payload', 400);
        }

        $eventId = $event['id'] ?? null;
        $eventType = $event['type'] ?? null;

        // Log webhook request
        $webhookLog = $this->webhookService->logWebhook(
            $request,
            'stripe',
            $eventId,
            $eventType
        );

        // Verify signature
        $signatureValid = $this->verifyStripeSignature($payload, $sigHeader, $secret);
        $webhookLog->update(['signature_valid' => $signatureValid]);

        if (! $signatureValid) {
            Log::warning('Stripe webhook signature verification failed', [
                'ip' => $request->ip(),
            ]);
            $this->webhookService->updateWebhookLog($webhookLog, 400, 'Invalid signature', 'Signature verification failed');
            return response('Invalid signature', 400);
        }

        if (! $eventId) {
            $this->webhookService->updateWebhookLog($webhookLog, 400, 'Missing event ID', 'Missing event ID');
            return response('Missing event ID', 400);
        }

        // Idempotency: already processed?
        if ($this->webhookService->isDuplicateEvent($eventId, 'stripe')) {
            Log::info('Stripe webhook already processed', ['event_id' => $eventId]);
            $this->webhookService->updateWebhookLog($webhookLog, 200, 'Already processed');
            return response('Already processed', 200);
        }

        try {
            match ($eventType) {
                'payment_intent.succeeded' => $this->handlePaymentSucceeded($event, $eventId, $webhookLog),
                'charge.refunded' => $this->handleRefund($event, $webhookLog),
                default => null,
            };
            
            $this->webhookService->updateWebhookLog($webhookLog, 200, 'OK');
        } catch (Throwable $e) {
            Log::error('Stripe webhook handler error', [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);

            $this->webhookService->updateWebhookLog($webhookLog, 500, 'Handler error', $e->getMessage());
            return response('Handler error', 500);
        }

        return response('OK', 200);
    }

    // -----------------------------------------------------------------------
    // Event handlers
    // -----------------------------------------------------------------------

    private function handlePaymentSucceeded(array $event, string $eventId, $webhookLog): void
    {
        $intent = $event['data']['object'] ?? [];
        $providerTxnId = $intent['id'] ?? null;
        $amountCents = (int) ($intent['amount'] ?? 0);
        $amount = $amountCents / 100; // Stripe stores in cents
        $metadata = $intent['metadata'] ?? [];
        $bookingId = $metadata['booking_id'] ?? null;

        if (! $bookingId) {
            Log::warning('Stripe payment_intent.succeeded: missing booking_id in metadata', compact('eventId'));
            return;
        }

        $booking = Booking::query()->with(['payment', 'ride'])->find((int) $bookingId);

        if (! $booking) {
            Log::warning("Stripe webhook: booking #{$bookingId} not found");
            return;
        }

        // Create payment event
        $paymentEvent = $this->webhookService->createPaymentEvent(
            'stripe',
            'payment_intent.succeeded',
            $event,
            $booking->payment?->id,
            $booking->id,
        );

        DB::transaction(function () use ($booking, $amount, $providerTxnId, $eventId, $paymentEvent) {
            $payment = $this->upsertPayment($booking, $amount, 'stripe', $providerTxnId, $eventId);
            $paymentEvent->update(['payment_id' => $payment->id]);

            $this->ledgerService->recordPaymentReceived($payment, 'stripe');

            $driverId = $booking->ride?->driver_id;
            if ($driverId) {
                $this->walletService->creditPending((int) $driverId, round($amount * 0.92, 2));
            }
            
            // Process payment event
            $this->webhookService->processPaymentEvent($paymentEvent);
        });

        Log::info('Stripe payment processed', ['booking_id' => $booking->id, 'amount' => $amount]);
    }

    private function handleRefund(array $event, $webhookLog): void
    {
        $charge = $event['data']['object'] ?? [];
        $intentId = $charge['payment_intent'] ?? null;

        if (! $intentId) {
            return;
        }

        $payment = Payment::query()->where('provider_transaction_id', $intentId)->first();

        if (! $payment) {
            Log::warning("Stripe refund: payment not found for intent {$intentId}");
            return;
        }

        // Create payment event
        $paymentEvent = $this->webhookService->createPaymentEvent(
            'stripe',
            'charge.refunded',
            $event,
            $payment->id,
            $payment->booking_id,
            $payment->trip_id,
        );

        DB::transaction(function () use ($payment, $paymentEvent) {
            $payment->update([
                'status' => 'REFUNDED',
                'refunded_at' => now(),
                'verification_status' => 'verified',
            ]);

            $this->ledgerService->recordRefund($payment);

            // Freeze driver funds if they were already in their available balance
            $driverId = $payment->booking?->ride?->driver_id;
            if ($driverId) {
                try {
                    $this->walletService->freeze((int) $driverId, (float) $payment->driver_amount);
                    Log::info("Driver #{$driverId} funds frozen due to refund", ['payment_id' => $payment->id]);
                } catch (Throwable $e) {
                    Log::warning('Could not freeze driver funds for refund', [
                        'driver_id' => $driverId,
                        'payment_id' => $payment->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // Process payment event
            $this->webhookService->processPaymentEvent($paymentEvent);
        });
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function upsertPayment(
        Booking $booking,
        float $amount,
        string $provider,
        ?string $providerTxnId,
        string $eventId
    ): Payment {
        $commonFields = [
            'payment_provider' => $provider,
            'provider_transaction_id' => $providerTxnId,
            'webhook_event_id' => $eventId,
            'verification_status' => 'verified',
            'status' => 'COMPLETED',
            'paid_at' => now(),
        ];

        if ($booking->payment) {
            $booking->payment->fill($commonFields)->save();

            return $booking->payment->fresh();
        }

        return Payment::create(array_merge($commonFields, [
            'booking_id' => $booking->id,
            'user_id' => $booking->user_id,
            'amount' => $amount,
            'platform_fee' => round($amount * 0.08, 2),
            'driver_amount' => round($amount * 0.92, 2),
            'currency' => $booking->currency ?? 'RWF',
            'payment_method' => $provider,
        ]));
    }

    /**
     * Verify Stripe webhook signature using HMAC-SHA256.
     * Mirrors the algorithm from the official stripe-php SDK.
     */
    private function verifyStripeSignature(string $payload, string $sigHeader, string $secret): bool
    {
        if (! $secret) {
            // Allow unsigned webhooks only in local environment
            return app()->environment('local');
        }

        $parts = explode(',', $sigHeader);
        $timestamp = null;
        $signatures = [];

        foreach ($parts as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            if ($key === 't') {
                $timestamp = $value;
            }
            if ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if (! $timestamp || empty($signatures)) {
            return false;
        }

        // Reject stale webhooks (> 5 minutes)
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }

        return false;
    }
}
