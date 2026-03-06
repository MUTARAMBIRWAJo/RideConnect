<?php

namespace App\Events\Domain;

class PaymentCaptured extends DomainEvent
{
    public const VERSION = 1;

    public function __construct(
        public readonly int    $paymentId,
        public readonly int    $bookingId,
        public readonly int    $userId,
        public readonly float  $amount,
        public readonly string $currency,
        public readonly string $provider,
        public readonly string $providerTransactionId,
        public readonly string $capturedAt,
    ) {
        parent::__construct();
    }

    public function aggregateId(): string   { return (string) $this->paymentId; }
    public function aggregateType(): string { return 'payment'; }

    public function toPayload(): array
    {
        return [
            'payment_id'              => $this->paymentId,
            'booking_id'              => $this->bookingId,
            'user_id'                 => $this->userId,
            'amount'                  => $this->amount,
            'currency'                => $this->currency,
            'provider'                => $this->provider,
            'provider_transaction_id' => $this->providerTransactionId,
            'captured_at'             => $this->capturedAt,
        ];
    }
}
