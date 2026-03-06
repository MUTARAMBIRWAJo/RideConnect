<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\LedgerService;
use App\Services\WalletService;
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
    ) {
    }

    public function handle(Request $request): Response
    {
        // MTN MoMo uses a shared API key passed in the callback header
        $apiKey      = $request->header('X-Callback-Api-Key', '');
        $expectedKey = config('services.mtn.callback_api_key', '');

        if (! $expectedKey || ! hash_equals($expectedKey, $apiKey)) {
            Log::warning('MTN webhook: invalid or missing callback API key', ['ip' => $request->ip()]);
            return response('Forbidden', 403);
        }

        $payload              = $request->all();
        $externalId           = $payload['externalId']            ?? null; // maps to booking_id
        $status               = strtolower($payload['status']     ?? '');
        $amount               = (float) ($payload['amount']       ?? 0);
        $financialTransactionId = $payload['financialTransactionId'] ?? null;

        if (! $externalId) {
            return response('Missing externalId', 400);
        }

        // Idempotency check
        if ($financialTransactionId && Payment::query()->where('webhook_event_id', $financialTransactionId)->exists()) {
            Log::info("MTN webhook already processed", ['financial_transaction_id' => $financialTransactionId]);
            return response('Already processed', 200);
        }

        try {
            match ($status) {
                'successful' => $this->handleSuccess($payload, $financialTransactionId),
                'failed'     => $this->handleFailure($payload),
                default      => null,
            };
        } catch (Throwable $e) {
            Log::error("MTN webhook handler error", [
                'external_id' => $externalId,
                'status'      => $status,
                'error'       => $e->getMessage(),
            ]);
            return response('Handler error', 500);
        }

        return response('Accepted', 202);
    }

    // -----------------------------------------------------------------------
    // Event handlers
    // -----------------------------------------------------------------------

    private function handleSuccess(array $payload, ?string $financialTransactionId): void
    {
        $bookingId  = $payload['externalId'] ?? null;
        $amount     = (float) ($payload['amount'] ?? 0);
        $payerPhone = $payload['payer']['partyId'] ?? null;

        $booking = Booking::query()->with(['payment', 'ride'])->find((int) $bookingId);

        if (! $booking) {
            Log::warning("MTN webhook: booking #{$bookingId} not found");
            return;
        }

        DB::transaction(function () use ($booking, $amount, $financialTransactionId, $payload, $payerPhone) {
            $commonFields = [
                'payment_provider'        => 'mtn_momo',
                'provider_transaction_id' => $payload['externalId'],
                'webhook_event_id'        => $financialTransactionId,
                'verification_status'     => 'verified',
                'status'                  => 'COMPLETED',
                'paid_at'                 => now(),
            ];

            if ($booking->payment) {
                $booking->payment->fill($commonFields)->save();
                $payment = $booking->payment->fresh();
            } else {
                $payment = Payment::create(array_merge($commonFields, [
                    'booking_id'      => $booking->id,
                    'user_id'         => $booking->user_id,
                    'amount'          => $amount,
                    'platform_fee'    => round($amount * 0.08, 2),
                    'driver_amount'   => round($amount * 0.92, 2),
                    'currency'        => $payload['currency'] ?? 'RWF',
                    'payment_method'  => 'mtn_momo',
                    'payment_details' => json_encode(['payer_phone' => $payerPhone]),
                ]));
            }

            $this->ledgerService->recordPaymentReceived($payment, 'mtn_momo');

            $driverId = $booking->ride?->driver_id;
            if ($driverId) {
                $this->walletService->creditPending((int) $driverId, round($amount * 0.92, 2));
            }
        });

        Log::info("MTN payment processed", ['booking_id' => $booking->id, 'amount' => $amount]);
    }

    private function handleFailure(array $payload): void
    {
        $bookingId = $payload['externalId'] ?? null;

        if (! $bookingId) {
            return;
        }

        $payment = Payment::query()
            ->whereHas('booking', fn ($q) => $q->where('id', (int) $bookingId))
            ->first();

        if ($payment) {
            $payment->update([
                'status'              => 'FAILED',
                'verification_status' => 'failed',
            ]);
        }

        Log::info("MTN payment failed", compact('bookingId'));
    }
}
