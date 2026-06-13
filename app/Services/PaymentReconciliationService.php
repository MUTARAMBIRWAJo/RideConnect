<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentReconciliationLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentReconciliationService
{
    /**
     * Reconcile payments for a specific date and provider
     */
    public function reconcilePayments(string $provider, \DateTime $date): array
    {
        $results = [
            'total' => 0,
            'matched' => 0,
            'mismatched' => 0,
            'missing' => 0,
            'discrepancies' => [],
        ];

        $payments = Payment::where('payment_provider', $provider)
            ->whereDate('paid_at', $date)
            ->where('status', 'COMPLETED')
            ->get();

        foreach ($payments as $payment) {
            $results['total']++;
            
            $reconciliation = $this->reconcilePayment($payment);
            
            match ($reconciliation->status) {
                'matched' => $results['matched']++,
                'mismatched' => $results['mismatched']++,
                'missing' => $results['missing']++,
            };

            if ($reconciliation->status !== 'matched') {
                $results['discrepancies'][] = [
                    'payment_id' => $payment->id,
                    'provider_transaction_id' => $payment->provider_transaction_id,
                    'expected' => $payment->amount,
                    'actual' => $reconciliation->actual_amount,
                    'discrepancy' => $reconciliation->discrepancy_amount,
                    'reason' => $reconciliation->discrepancy_reason,
                ];
            }
        }

        return $results;
    }

    /**
     * Reconcile individual payment
     */
    public function reconcilePayment(Payment $payment): PaymentReconciliationLog
    {
        $existing = PaymentReconciliationLog::where('payment_id', $payment->id)
            ->whereDate('reconciliation_date', today())
            ->first();

        if ($existing) {
            return $existing;
        }

        $providerData = $this->fetchProviderData($payment);
        $expectedAmount = (float) $payment->amount;
        $actualAmount = $providerData['amount'] ?? $expectedAmount;
        $discrepancy = abs($expectedAmount - $actualAmount);

        $status = match (true) {
            $discrepancy > 0.01 => 'mismatched',
            $providerData === null => 'missing',
            default => 'matched',
        };

        return PaymentReconciliationLog::create([
            'reconciliation_id' => Str::uuid(),
            'payment_provider' => $payment->payment_provider,
            'reconciliation_date' => today(),
            'payment_id' => $payment->id,
            'provider_transaction_id' => $payment->provider_transaction_id,
            'expected_amount' => $expectedAmount,
            'actual_amount' => $actualAmount,
            'currency' => $payment->currency ?? 'RWF',
            'status' => $status,
            'discrepancy_amount' => $discrepancy,
            'discrepancy_reason' => $status !== 'matched' ? $this->getDiscrepancyReason($status, $payment, $providerData) : null,
            'provider_data' => $providerData,
            'system_data' => [
                'payment_status' => $payment->status,
                'verification_status' => $payment->verification_status,
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ],
            'reconciled_at' => $status === 'matched' ? now() : null,
            'reconciliation_started_at' => now(),
        ]);
    }

    /**
     * Fetch payment data from provider (placeholder - implement based on provider APIs)
     */
    private function fetchProviderData(Payment $payment): ?array
    {
        // This would integrate with Stripe/MTN APIs to verify payment details
        // For now, return null to indicate missing provider data
        // In production, implement actual API calls to verify payments
        
        try {
            return match ($payment->payment_provider) {
                'stripe' => $this->fetchStripePayment($payment),
                'mtn_momo' => $this->fetchMTNPayment($payment),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::error('Failed to fetch provider payment data', [
                'payment_id' => $payment->id,
                'provider' => $payment->payment_provider,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Fetch payment from Stripe API
     */
    private function fetchStripePayment(Payment $payment): ?array
    {
        // Implement Stripe API call to verify payment
        // This is a placeholder - implement actual Stripe SDK integration
        return null;
    }

    /**
     * Fetch payment from MTN MoMo API
     */
    private function fetchMTNPayment(Payment $payment): ?array
    {
        // Implement MTN MoMo API call to verify payment
        // This is a placeholder - implement actual MTN API integration
        return null;
    }

    /**
     * Get discrepancy reason
     */
    private function getDiscrepancyReason(string $status, Payment $payment, ?array $providerData): string
    {
        $providerAmount = $providerData['amount'] ?? 'N/A';
        
        return match ($status) {
            'mismatched' => "Amount mismatch: system={$payment->amount}, provider={$providerAmount}",
            'missing' => 'Payment not found in provider system',
            default => 'Unknown discrepancy',
        };
    }

    /**
     * Get reconciliation summary for a date range
     */
    public function getReconciliationSummary(\DateTime $startDate, \DateTime $endDate, ?string $provider = null): array
    {
        $query = PaymentReconciliationLog::whereBetween('reconciliation_date', [$startDate, $endDate]);

        if ($provider) {
            $query->where('payment_provider', $provider);
        }

        $logs = $query->get();

        return [
            'total_payments' => $logs->count(),
            'matched' => $logs->where('status', 'matched')->count(),
            'mismatched' => $logs->where('status', 'mismatched')->count(),
            'missing' => $logs->where('status', 'missing')->count(),
            'total_discrepancy_amount' => $logs->sum('discrepancy_amount'),
            'by_provider' => $logs->groupBy('payment_provider')->map(function ($group) {
                return [
                    'total' => $group->count(),
                    'matched' => $group->where('status', 'matched')->count(),
                    'mismatched' => $group->where('status', 'mismatched')->count(),
                    'missing' => $group->where('status', 'missing')->count(),
                    'discrepancy_amount' => $group->sum('discrepancy_amount'),
                ];
            }),
        ];
    }
}
