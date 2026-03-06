<?php

namespace App\Modules\Finance\Contracts;

use App\Models\Payment;

interface PaymentRepositoryInterface
{
    public function findByWebhookEventId(string $webhookEventId): ?Payment;

    public function upsertFromWebhook(array $data): Payment;

    public function markVerified(int $paymentId, string $verificationStatus): void;

    public function getByDateRange(\DateTimeInterface $from, \DateTimeInterface $to): \Illuminate\Database\Eloquent\Collection;

    public function sumByProviderAndDate(string $provider, string $date): float;
}
