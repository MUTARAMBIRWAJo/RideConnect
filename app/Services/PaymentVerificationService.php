<?php

namespace App\Services;

use App\Events\Domain\PaymentVerified;
use App\Models\Payment;
use App\Models\PaymentVerification;
use Illuminate\Support\Facades\DB;

class PaymentVerificationService
{
    public function verify(int $paymentId, ?int $verifiedBy, string $method = 'manual', ?string $notes = null): Payment
    {
        return DB::transaction(function () use ($paymentId, $verifiedBy, $method, $notes): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($paymentId);
            $trip = null;

            $payment->update([
                'status' => 'paid',
                'verification_status' => 'verified',
                'paid_at' => $payment->paid_at ?: now(),
            ]);

            PaymentVerification::query()->create([
                'payment_id' => $payment->id,
                'verified_by' => $verifiedBy,
                'verification_method' => $method,
                'status' => 'verified',
                'notes' => $notes,
                'verified_at' => now(),
            ]);

            if ($payment->trip_id) {
                $payment->trip()->update(['payment_status' => 'paid']);
            }
            if ($payment->booking_id) {
                $trip = \App\Models\Trip::query()->where('booking_id', $payment->booking_id)->first();
                $trip?->update(['payment_status' => 'paid']);
            }

            event(new PaymentVerified($payment->id, $payment->trip_id ? (int) $payment->trip_id : ($trip?->id ? (int) $trip->id : null)));

            return $payment->fresh();
        });
    }
}
