<?php

namespace App\Modules\Finance\Services;

use App\Events\Domain\EscrowCredited;
use App\Events\Domain\PaymentCaptured;
use App\Modules\Finance\Contracts\LedgerRepositoryInterface;
use App\Modules\Finance\Contracts\PaymentRepositoryInterface;
use App\Modules\Finance\DTOs\PaymentDTO;
use App\Services\EventSourcing\EventDispatcherService;
use App\Services\LedgerService;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;

/**
 * FinanceService — Application-layer facade for all financial operations.
 *
 * Controllers must only interact with this service, never directly with
 * LedgerService, WalletService, or any Eloquent model.
 *
 * Architecture: Controller → FinanceService → Domain Services → Repositories
 */
class FinanceService
{
    public function __construct(
        private readonly LedgerService           $ledgerService,
        private readonly WalletService           $walletService,
        private readonly LedgerRepositoryInterface $ledgerRepo,
        private readonly PaymentRepositoryInterface $paymentRepo,
        private readonly EventDispatcherService  $eventDispatcher,
    ) {}

    /**
     * Record a captured payment from a webhook and credit escrow.
     */
    public function capturePayment(PaymentDTO $dto): array
    {
        return DB::transaction(function () use ($dto) {
            // Idempotency: skip if event already processed
            if ($dto->webhookEventId && $this->paymentRepo->findByWebhookEventId($dto->webhookEventId)) {
                return ['idempotent' => true, 'payment_id' => null];
            }

            $payment = $this->paymentRepo->upsertFromWebhook($dto->toArray());

            // Record to double-entry ledger
            $this->ledgerService->recordPaymentReceived($payment, $dto->provider);

            // Credit 92% to driver escrow (pending)
            $driverShare = round($payment->amount * 0.92, 2);
            // Resolve booking → driver relationship
            $driverId = $payment->booking?->ride?->driver_id ?? null;
            if ($driverId) {
                $this->walletService->creditPending($driverId, $driverShare);
            }

            // Emit domain events within same transaction (outbox)
            $this->eventDispatcher->dispatch(new PaymentCaptured(
                paymentId:             $payment->id,
                bookingId:             (int) $payment->booking_id,
                userId:                (int) $payment->user_id,
                amount:                (float) $payment->amount,
                currency:              'RWF',
                provider:              $dto->provider,
                providerTransactionId: $dto->providerTransactionId ?? '',
                capturedAt:            now()->toIso8601String(),
            ));

            if ($driverId) {
                $this->eventDispatcher->dispatch(new EscrowCredited(
                    paymentId:  $payment->id,
                    driverId:   $driverId,
                    amount:     $driverShare,
                    currency:   'RWF',
                    creditedAt: now()->toIso8601String(),
                ));
            }

            return ['idempotent' => false, 'payment_id' => $payment->id];
        });
    }

    public function getEscrowBalance(): float
    {
        return $this->ledgerRepo->getPlatformEscrowBalance();
    }

    public function getPlatformRevenue(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): float
    {
        return $this->ledgerRepo->getPlatformRevenue($from, $to);
    }
}
