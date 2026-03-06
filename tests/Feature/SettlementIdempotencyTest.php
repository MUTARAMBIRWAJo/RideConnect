<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\DriverPayout;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Modules\Settlement\Services\SettlementService;
use App\Modules\Settlement\DTOs\SettlementResultDTO;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SettlementIdempotencyTest
 *
 * Confirms that calling settleDriver() twice for the same driver + date
 * does NOT create a duplicate DriverPayout record.
 * The second call must return the existing record with isIdempotent=true.
 */
class SettlementIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private SettlementService $settlementService;
    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settlementService = app(SettlementService::class);

        // Create minimal driver with associated user
        $user   = User::factory()->create(['name' => 'Test Driver']);
        $this->driver = Driver::factory()->create(['user_id' => $user->id]);

        // Seed required ledger accounts (settlement service needs them)
        $this->seedLedgerAccounts();
    }

    public function test_first_settlement_creates_payout_record(): void
    {
        $date   = '2025-06-01';
        $income = 200_000.0;

        $result = $this->settlementService->settleDriver($this->driver->id, $income, $date);

        $this->assertInstanceOf(SettlementResultDTO::class, $result);
        $this->assertFalse($result->isIdempotent, 'First call must not be idempotent');

        $this->assertDatabaseHas('driver_payouts', [
            'driver_id'    => $this->driver->id,
            'payout_date'  => $date,
        ]);
    }

    public function test_second_settlement_same_date_is_idempotent(): void
    {
        $date   = '2025-06-02';
        $income = 150_000.0;

        // First call
        $this->settlementService->settleDriver($this->driver->id, $income, $date);

        // Second call — must return existing record
        $result = $this->settlementService->settleDriver($this->driver->id, $income, $date);

        $this->assertTrue($result->isIdempotent, 'Second call must be idempotent');

        // Only one payout record must exist
        $count = DriverPayout::where('driver_id', $this->driver->id)
            ->where('payout_date', $date)
            ->count();

        $this->assertSame(1, $count, 'Exactly one payout record must exist for this date');
    }

    public function test_different_date_creates_new_payout(): void
    {
        $income = 100_000.0;

        $this->settlementService->settleDriver($this->driver->id, $income, '2025-07-01');
        $result = $this->settlementService->settleDriver($this->driver->id, $income, '2025-07-02');

        $this->assertFalse($result->isIdempotent, 'Different date must create a new record');

        $count = DriverPayout::where('driver_id', $this->driver->id)->count();
        $this->assertSame(2, $count);
    }

    public function test_commission_is_8_percent_of_gross(): void
    {
        $income = 500_000.0;
        $result = $this->settlementService->settleDriver($this->driver->id, $income, '2025-08-01');

        // 8% commission
        $this->assertEqualsWithDelta(40_000.0, $result->commissionAmount, 0.01);
    }

    public function test_net_payout_is_gross_minus_commission_minus_tax(): void
    {
        $income = 500_000.0;
        $result = $this->settlementService->settleDriver($this->driver->id, $income, '2025-08-02');

        // Net must be less than gross - commission
        $this->assertLessThan($income - $result->commissionAmount, $result->netPayout);
        $this->assertGreaterThan(0.0, $result->netPayout);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function seedLedgerAccounts(): void
    {
        $accounts = [
            ['code' => '1001', 'name' => 'Escrow',              'type' => 'asset',     'owner_type' => 'platform', 'owner_id' => null],
            ['code' => '2001', 'name' => 'Driver Payable',      'type' => 'liability', 'owner_type' => 'platform', 'owner_id' => null],
            ['code' => '4001', 'name' => 'Platform Commission', 'type' => 'revenue',   'owner_type' => 'platform', 'owner_id' => null],
            ['code' => '2002', 'name' => 'Tax Payable',         'type' => 'liability', 'owner_type' => 'platform', 'owner_id' => null],
        ];

        foreach ($accounts as $account) {
            LedgerAccount::firstOrCreate(
                ['code' => $account['code']],
                array_merge($account, ['balance' => 0, 'currency' => 'RWF', 'is_active' => true]),
            );
        }
    }
}
