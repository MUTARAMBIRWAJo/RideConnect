<?php

namespace App\Modules\Finance\DTOs;

readonly class PaymentDTO
{
    public function __construct(
        public int     $bookingId,
        public int     $userId,
        public float   $amount,
        public string  $currency,
        public string  $provider,           // 'stripe' | 'mtn_momo'
        public string  $status,             // 'pending' | 'completed' | 'failed' | 'refunded'
        public ?string $webhookEventId,
        public ?string $providerTransactionId,
        public ?string $verificationStatus, // 'verified' | 'unverified'
    ) {}

    public static function fromWebhookPayload(array $payload, string $provider): self
    {
        return new self(
            bookingId:             (int) ($payload['booking_id']    ?? 0),
            userId:                (int) ($payload['user_id']       ?? 0),
            amount:                (float) ($payload['amount']      ?? 0),
            currency:              $payload['currency']             ?? 'RWF',
            provider:              $provider,
            status:                $payload['status']               ?? 'pending',
            webhookEventId:        $payload['event_id']             ?? null,
            providerTransactionId: $payload['transaction_id']       ?? null,
            verificationStatus:    'verified',
        );
    }

    public function toArray(): array
    {
        return [
            'booking_id'              => $this->bookingId,
            'user_id'                 => $this->userId,
            'amount'                  => $this->amount,
            'currency'                => $this->currency,
            'payment_provider'        => $this->provider,
            'status'                  => $this->status,
            'webhook_event_id'        => $this->webhookEventId,
            'provider_transaction_id' => $this->providerTransactionId,
            'verification_status'     => $this->verificationStatus,
        ];
    }
}
