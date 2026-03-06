<?php

namespace App\Modules\Tax\Services;

use App\Events\Domain\TaxComputed;
use App\Models\LedgerAccount;
use App\Modules\Tax\Contracts\TaxRuleRepositoryInterface;
use App\Modules\Tax\DTOs\TaxBreakdownDTO;
use App\Services\LedgerService;
use Illuminate\Support\Facades\DB;

/**
 * TaxService — Dynamic tax computation engine.
 *
 * Supports Rwanda RRA compliance:
 *  - VAT 18% on ride fares
 *  - WHT 15% on commission income
 *  - WHT 5% on driver payout disbursements
 *
 * Rules are dynamically loaded from tax_rules table.
 * To add a new tax, insert a row in tax_rules — no code changes needed.
 */
class TaxService
{
    public function __construct(
        private readonly TaxRuleRepositoryInterface $taxRuleRepo,
        private readonly LedgerService              $ledgerService,
    ) {}

    /**
     * Calculate all applicable taxes on a ride fare.
     * Returns a breakdown with line items per rule.
     */
    public function calculateRideTax(float $grossAmount, string $jurisdiction = 'RW'): TaxBreakdownDTO
    {
        return $this->compute($grossAmount, 'ride', $jurisdiction);
    }

    /**
     * Calculate tax on platform commission earnings.
     */
    public function calculateCommissionTax(float $commissionAmount, string $jurisdiction = 'RW'): TaxBreakdownDTO
    {
        return $this->compute($commissionAmount, 'commission', $jurisdiction);
    }

    /**
     * Calculate withholding tax on a driver payout.
     */
    public function calculatePayoutTax(float $payoutAmount, string $jurisdiction = 'RW'): TaxBreakdownDTO
    {
        return $this->compute($payoutAmount, 'payout', $jurisdiction);
    }

    /**
     * Generate a complete tax summary for a given period.
     *
     * @return array{period: string, total_vat: float, total_wht: float, total_tax: float, breakdown: array}
     */
    public function generateTaxBreakdown(string $fromDate, string $toDate, string $jurisdiction = 'RW'): array
    {
        $rules = $this->taxRuleRepo->getActiveRulesFor('all', $jurisdiction);

        // Summarise tax from ledger: look for entries in the Tax Payable account
        $taxAccount = LedgerAccount::where('name', 'Tax Payable')
            ->where('owner_type', 'platform')
            ->first();

        $totalTaxCollected = 0.0;

        if ($taxAccount) {
            $totalTaxCollected = (float) $taxAccount->entries()
                ->whereBetween('created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
                ->sum('credit');
        }

        return [
            'period'              => "{$fromDate} – {$toDate}",
            'jurisdiction'        => $jurisdiction,
            'rules_applied'       => $rules->pluck('tax_name')->toArray(),
            'total_tax_collected' => $totalTaxCollected,
            'tax_account_balance' => $taxAccount ? (float) $taxAccount->getRunningBalance() : 0.0,
        ];
    }

    /**
     * Record tax in the double-entry ledger and emit TaxComputed event.
     *
     * Debit:  Commission/Income Account  +taxAmount
     * Credit: Tax Payable Account        +taxAmount
     */
    public function recordTaxToLedger(
        TaxBreakdownDTO $breakdown,
        int $referenceId,
        string $referenceType,
        ?int $createdBy = null
    ): void {
        if ($breakdown->totalTax <= 0) return;

        // Resolve source account based on what the tax applies to
        $sourceAccountName = match ($breakdown->appliesTo) {
            'commission' => LedgerService::REVENUE_ACCOUNT,
            'ride'       => LedgerService::ESCROW_ACCOUNT,
            default      => LedgerService::REVENUE_ACCOUNT,
        };

        $sourceAccount = $this->ledgerService->getPlatformAccount($sourceAccountName);
        $taxAccount    = \App\Models\LedgerAccount::firstOrCreate(
            ['name' => 'Tax Payable', 'owner_type' => 'platform'],
            ['type' => 'liability', 'currency' => 'RWF', 'is_active' => true]
        );

        $ref = ['reference_type' => $referenceType, 'reference_id' => $referenceId];

        $this->ledgerService->record(
            "Tax withheld on {$breakdown->appliesTo}: {$breakdown->totalTax} RWF",
            [
                array_merge(['account_id' => $sourceAccount->id, 'debit' => $breakdown->totalTax, 'credit' => 0, 'description' => "Tax deduction ({$breakdown->appliesTo})"], $ref),
                array_merge(['account_id' => $taxAccount->id,    'debit' => 0, 'credit' => $breakdown->totalTax, 'description' => "Tax payable credit"], $ref),
            ],
            $createdBy
        );

        event(new TaxComputed(
            referenceId:   $referenceId,
            referenceType: $referenceType,
            appliesTo:     $breakdown->appliesTo,
            grossAmount:   $breakdown->grossAmount,
            taxAmount:     $breakdown->totalTax,
            netAmount:     $breakdown->netAmount,
            jurisdiction:  $breakdown->jurisdiction,
            lineItems:     $breakdown->lineItems,
            currency:      'RWF',
        ));
    }

    // -----------------------------------------------------------------------

    private function compute(float $grossAmount, string $appliesTo, string $jurisdiction): TaxBreakdownDTO
    {
        $rules     = $this->taxRuleRepo->getActiveRulesFor($appliesTo, $jurisdiction);
        $totalTax  = 0.0;
        $lineItems = [];

        foreach ($rules as $rule) {
            $taxAmount = round($grossAmount * $rule->decimal_rate, 2);
            $totalTax += $taxAmount;

            $lineItems[] = [
                'rule_name'    => $rule->tax_name,
                'percentage'   => $rule->percentage,
                'amount'       => $taxAmount,
                'jurisdiction' => $jurisdiction,
            ];
        }

        return new TaxBreakdownDTO(
            grossAmount:  $grossAmount,
            appliesTo:    $appliesTo,
            jurisdiction: $jurisdiction,
            totalTax:     round($totalTax, 2),
            netAmount:    round($grossAmount - $totalTax, 2),
            lineItems:    $lineItems,
        );
    }
}
