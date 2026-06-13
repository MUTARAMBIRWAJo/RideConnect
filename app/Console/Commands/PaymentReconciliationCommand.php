<?php

namespace App\Console\Commands;

use App\Services\PaymentReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PaymentReconciliationCommand extends Command
{
    protected $signature = 'payment:reconcile {--provider= : Specific payment provider to reconcile} {--date= : Specific date to reconcile (YYYY-MM-DD)}';
    
    protected $description = 'Reconcile payments with payment providers';

    public function __construct(
        private readonly PaymentReconciliationService $reconciliationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $provider = $this->option('provider');
        $date = $this->option('date') ? \DateTime::createFromFormat('Y-m-d', $this->option('date')) : today();

        if (!$date) {
            $this->error('Invalid date format. Use YYYY-MM-DD.');
            return Command::FAILURE;
        }

        $this->info("Starting payment reconciliation for date: {$date->format('Y-m-d')}" . ($provider ? " (Provider: {$provider})" : ''));

        try {
            if ($provider) {
                $results = $this->reconciliationService->reconcilePayments($provider, $date);
                $this->displayResults($provider, $date, $results);
            } else {
                // Reconcile all providers
                $providers = ['stripe', 'mtn_momo'];
                foreach ($providers as $prov) {
                    $results = $this->reconciliationService->reconcilePayments($prov, $date);
                    $this->displayResults($prov, $date, $results);
                }
            }

            $this->info('Payment reconciliation completed successfully.');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Payment reconciliation failed: {$e->getMessage()}");
            Log::error('Payment reconciliation command failed', [
                'provider' => $provider,
                'date' => $date->format('Y-m-d'),
                'error' => $e->getMessage(),
            ]);
            return Command::FAILURE;
        }
    }

    private function displayResults(string $provider, \DateTime $date, array $results): void
    {
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Payments', $results['total']],
                ['Matched', $results['matched']],
                ['Mismatched', $results['mismatched']],
                ['Missing', $results['missing']],
            ]
        );

        if (!empty($results['discrepancies'])) {
            $this->warn('Discrepancies found:');
            foreach ($results['discrepancies'] as $discrepancy) {
                $this->line("  - Payment ID: {$discrepancy['payment_id']}, Transaction: {$discrepancy['provider_transaction_id']}");
                $this->line("    Expected: {$discrepancy['expected']}, Actual: {$discrepancy['actual']}, Discrepancy: {$discrepancy['discrepancy']}");
                $this->line("    Reason: {$discrepancy['reason']}");
            }
        }
    }
}
