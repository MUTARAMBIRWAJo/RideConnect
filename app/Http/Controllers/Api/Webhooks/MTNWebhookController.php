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

class MTNWebhookController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledgerService,
        private readonly WalletService $walletService,
        private readonly PaymentWebhookService $webhookService,
    ) {}

    public function handle(Request $request): Response
    {
        // MTN MoMo uses a shared API key passed in the callback header
        $apiKey = $request->header('X-Callback-Api-Key', '');
        $expectedKey = config('services.mtn.callback_api_key', '');

        $payload = $request->all();
        $externalId = $payload['externalId'] ?? null; // maps to booking_id
        $status = strtolower($payload['status'] ?? '');
        $amount = (float) ($payload['amount'] ?? 0);
        $financialTransactionId = $payload['financialTransactionId'] ?? null;

        // Log webhook request
        $webhookLog = $this->webhookService->logWebhook(
            $request,
            'mtn_momo',
            $financialTransactionId,
            $status
        );

        // Update signature validation
        $webhookLog->update([
            'signature_valid' => $expectedKey && hash_equals($expectedKey, $apiKey),
        ]);

        if (! $expectedKey || ! hash_equals($expectedKey, $apiKey)) {
            Log::warning('MTN webhook: invalid or missing callback API key', ['ip' => $request->ip()]);
            $this->webhookService->updateWebhookLog($webhookLog, 403, 'Forbidden', 'Invalid API key');
            return response('Forbidden', 403);
        }

        if (! $externalId) {
            $this->webhookService->updateWebhookLog($webhookLog, 400, 'Bad Request', 'Missing externalId');
            return response('Missing externalId', 400);
        }

        // Idempotency check - both in payments and webhook logs
        if ($financialTransactionId && $this->webhookService->isDuplicateEvent($financialTransactionId, 'mtn_momo')) {
            Log::info('MTN webhook already processed', ['financial_transaction_id' => $financialTransactionId]);
            $this->webhookService->updateWebhookLog($webhookLog, 200, 'Already processed');
            return response('Already processed', 200);
        }

        try {
            $result = match ($status) {
                'successful' => $this->handleSuccess($payload, $financialTransactionId, $webhookLog),
                'failed' => $this->handleFailure($payload, $webhookLog),
                default => null,
            };
            
            $this->webhookService->updateWebhookLog($webhookLog, 202, 'Accepted');
        } catch (Throwable $e) {
            Log::error('MTN webhook handler error', [
                'external_id' => $externalId,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);

            $this->webhookService->updateWebhookLog($webhookLog, 500, 'Handler error', $e->getMessage());
            return response('Handler error', 500);
        }

        return response('Accepted', 202);
    }

    // -----------------------------------------------------------------------
    // Event handlers
    // -----------------------------------------------------------------------

    private function handleSuccess(array $payload, ?string $financialTransactionId, $webhookLog): void
    {
        $bookingId = $payload['externalId'] ?? null;
        $amount = (float) ($payload['amount'] ?? 0);
        $payerPhone = $payload['payer']['partyId'] ?? null;

        $booking = Booking::query()->with(['payment', 'ride'])->find((int) $bookingId);

        if (! $booking) {
            Log::warning("MTN webhook: booking #{$bookingId} not found");
            return;
        }

        // Create payment event
        $paymentEvent = $this->webhookService->createPaymentEvent(
            'mtn_momo',
            'successful',
            $payload,
            $booking->payment?->id,
            $booking->id,
        );

        DB::transaction(function () use ($booking, $amount, $financialTransactionId, $payload, $payerPhone, $paymentEvent) {
            $commonFields = [
                'payment_provider' => 'mtn_momo',
                'provider_transaction_id' => $payload['externalId'],
                'webhook_event_id' => $financialTransactionId,
                'verification_status' => 'verified',
                'status' => 'COMPLETED',
                'paid_at' => now(),
            ];

            if ($booking->payment) {
                $booking->payment->fill($commonFields)->save();
                $payment = $booking->payment->fresh();
                $paymentEvent->update(['payment_id' => $payment->id]);
            } else {
                $payment = Payment::create(array_merge($commonFields, [
                    'booking_id' => $booking->id,
                    'user_id' => $booking->user_id,
                    'amount' => $amount,
                    'platform_fee' => round($amount * 0.08, 2),
                    'driver_amount' => round($amount * 0.92, 2),
                    'currency' => $payload['currency'] ?? 'RWF',
                    'payment_method' => 'mtn_momo',
                    'payment_details' => json_encode(['payer_phone' => $payerPhone]),
                ]));
                $paymentEvent->update(['payment_id' => $payment->id]);
            }

            $this->ledgerService->recordPaymentReceived($payment, 'mtn_momo');

            $driverId = $booking->ride?->driver_id;
            if ($driverId) {
                $this->walletService->creditPending((int) $driverId, round($amount * 0.92, 2));
            }
            
            // Process payment event
            $this->webhookService->processPaymentEvent($paymentEvent);
        });

        Log::info('MTN payment processed', ['booking_id' => $booking->id, 'amount' => $amount]);
    }

    private function handleFailure(array $payload, $webhookLog): void
    {
        $bookingId = $payload['externalId'] ?? null;

        if (! $bookingId) {
            return;
        }

        $payment = Payment::query()
            ->whereHas('booking', fn ($q) => $q->where('id', (int) $bookingId))
            ->first();

        // Create payment event
        $paymentEvent = $this->webhookService->createPaymentEvent(
            'mtn_momo',
            'failed',
            $payload,
            $payment?->id,
            (int) $bookingId,
        );

        if ($payment) {
            $payment->update([
                'status' => 'FAILED',
                'verification_status' => 'failed',
            ]);
            
            $this->webhookService->processPaymentEvent($paymentEvent);
        }

        Log::info('MTN payment failed', compact('bookingId'));
    }
}
