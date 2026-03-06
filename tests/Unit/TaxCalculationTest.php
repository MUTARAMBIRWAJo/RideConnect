<?php

namespace Tests\Unit;

use App\Models\TaxRule;
use App\Modules\Tax\DTOs\TaxBreakdownDTO;
use App\Modules\Tax\Repositories\TaxRuleRepository;
use App\Modules\Tax\Services\TaxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TaxCalculationTest
 *
 * Verifies that Rwanda tax rules (VAT 18%, WHT_COMMISSION 15%, WHT_PAYOUT 5%)
 * are calculated correctly given a variety of inputs.
 */
class TaxCalculationTest extends TestCase
{
    use RefreshDatabase;

    private TaxService $taxService;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed the 3 default Rwanda tax rules
        $this->seedTaxRules();

        $this->taxService = app(TaxService::class);
    }

    // -----------------------------------------------------------------------
    // VAT on ride fares (18%)
    // -----------------------------------------------------------------------

    public function test_ride_tax_returns_correct_vat_amount(): void
    {
        // 100,000 RWF * 18% = 18,000 RWF
        $breakdown = $this->taxService->calculateRideTax(100_000.0, 'RW');

        $this->assertInstanceOf(TaxBreakdownDTO::class, $breakdown);
        $this->assertCount(1, $breakdown->lineItems);

        $item = $breakdown->lineItems[0];
        $this->assertSame('VAT', $item['tax_code']);
        $this->assertEqualsWithDelta(100_000.0, $item['gross_amount'], 0.01);
        $this->assertEqualsWithDelta(18_000.0, $item['tax_amount'], 0.01);
        $this->assertEqualsWithDelta(18_000.0, $breakdown->totalTax, 0.01);
    }

    public function test_ride_tax_with_zero_amount(): void
    {
        $breakdown = $this->taxService->calculateRideTax(0.0, 'RW');

        $this->assertEqualsWithDelta(0.0, $breakdown->totalTax, 0.01);
    }

    public function test_ride_tax_with_fractional_amount(): void
    {
        // 50,333 RWF * 18% = 9,059.94 RWF
        $breakdown = $this->taxService->calculateRideTax(50_333.0, 'RW');

        $this->assertEqualsWithDelta(9_059.94, $breakdown->totalTax, 0.01);
    }

    // -----------------------------------------------------------------------
    // WHT on commission (15%)
    // -----------------------------------------------------------------------

    public function test_commission_tax_applies_15_pct_wht(): void
    {
        // 80,000 RWF * 15% = 12,000 RWF
        $breakdown = $this->taxService->calculateCommissionTax(80_000.0, 'RW');

        $this->assertInstanceOf(TaxBreakdownDTO::class, $breakdown);
        $this->assertEqualsWithDelta(12_000.0, $breakdown->totalTax, 0.01);

        $item = $breakdown->lineItems[0];
        $this->assertSame('WHT_COMMISSION', $item['tax_code']);
    }

    // -----------------------------------------------------------------------
    // WHT on payout (5%)
    // -----------------------------------------------------------------------

    public function test_payout_tax_applies_5_pct_wht(): void
    {
        // 500,000 RWF * 5% = 25,000 RWF
        $breakdown = $this->taxService->calculatePayoutTax(500_000.0, 'RW');

        $this->assertEqualsWithDelta(25_000.0, $breakdown->totalTax, 0.01);

        $item = $breakdown->lineItems[0];
        $this->assertSame('WHT_PAYOUT', $item['tax_code']);
    }

    // -----------------------------------------------------------------------
    // No matching rule → returns zero
    // -----------------------------------------------------------------------

    public function test_returns_zero_when_no_rule_matches_jurisdiction(): void
    {
        $breakdown = $this->taxService->calculateRideTax(100_000.0, 'UG');  // Uganda — no rules seeded

        $this->assertEqualsWithDelta(0.0, $breakdown->totalTax, 0.01);
        $this->assertEmpty($breakdown->lineItems);
    }

    // -----------------------------------------------------------------------
    // TaxBreakdownDTO JSON serialisation
    // -----------------------------------------------------------------------

    public function test_tax_breakdown_dto_serialises_to_json(): void
    {
        $breakdown = $this->taxService->calculateRideTax(100_000.0, 'RW');
        $json      = $breakdown->toJson();
        $decoded   = json_decode($json, true);

        $this->assertArrayHasKey('total_tax', $decoded);
        $this->assertArrayHasKey('line_items', $decoded);
        $this->assertEqualsWithDelta(18_000.0, $decoded['total_tax'], 0.01);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function seedTaxRules(): void
    {
        TaxRule::insert([
            [
                'applies_to'     => 'ride',
                'tax_name'       => 'Value Added Tax',
                'tax_code'       => 'VAT',
                'rate'           => 18.00,
                'jurisdiction'   => 'RW',
                'is_active'      => true,
                'effective_from' => '2024-01-01',
                'effective_to'   => null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'applies_to'     => 'commission',
                'tax_name'       => 'Withholding Tax on Commission',
                'tax_code'       => 'WHT_COMMISSION',
                'rate'           => 15.00,
                'jurisdiction'   => 'RW',
                'is_active'      => true,
                'effective_from' => '2024-01-01',
                'effective_to'   => null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'applies_to'     => 'payout',
                'tax_name'       => 'Withholding Tax on Payout',
                'tax_code'       => 'WHT_PAYOUT',
                'rate'           => 5.00,
                'jurisdiction'   => 'RW',
                'is_active'      => true,
                'effective_from' => '2024-01-01',
                'effective_to'   => null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
        ]);
    }
}
