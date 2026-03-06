<?php

namespace App\Modules\Finance\Repositories;

use App\Models\Payment;
use App\Modules\Finance\Contracts\PaymentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class PaymentRepository implements PaymentRepositoryInterface
{
    public function findByWebhookEventId(string $webhookEventId): ?Payment
    {
        return Payment::where('webhook_event_id', $webhookEventId)->first();
    }

    public function upsertFromWebhook(array $data): Payment
    {
        return Payment::updateOrCreate(
            ['webhook_event_id' => $data['webhook_event_id']],
            $data
        );
    }

    public function markVerified(int $paymentId, string $verificationStatus): void
    {
        Payment::where('id', $paymentId)->update(['verification_status' => $verificationStatus]);
    }

    public function getByDateRange(\DateTimeInterface $from, \DateTimeInterface $to): Collection
    {
        return Payment::whereBetween('created_at', [$from, $to])
            ->with('booking')
            ->orderBy('created_at')
            ->get();
    }

    public function sumByProviderAndDate(string $provider, string $date): float
    {
        return (float) Payment::where('payment_provider', $provider)
            ->whereDate('created_at', $date)
            ->where('status', 'completed')
            ->sum('amount');
    }
}
