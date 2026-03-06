<?php

namespace Tests\Unit;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Payment;
use App\Models\Booking;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class LedgerDoubleEntryTest extends TestCase
{
    use RefreshDatabase;

    private LedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledger = app(LedgerService::class);
    }

    // -----------------------------------------------------------------------
    // 1. Balanced transaction is recorded correctly
    // -----------------------------------------------------------------------

    public function test_balanced_transaction_is_recorded(): void
    {
        $asset     = $this->makeAccount('Cash', 'asset');
        $liability = $this->makeAccount('Revenue', 'revenue');

        $txn = $this->ledger->record('Test transaction', [
            ['account_id' => $asset->id,     'debit' => 1000.00, 'credit' => 0],
            ['account_id' => $liability->id, 'debit' => 0,       'credit' => 1000.00],
        ]);

        $this->assertInstanceOf(LedgerTransaction::class, $txn);
        $this->assertNotNull($txn->uuid);
        $this->assertCount(2, $txn->entries);
        $this->assertTrue($txn->isBalanced());
        $this->assertEquals(1000.00, $txn->total_debit);
        $this->assertEquals(1000.00, $txn->total_credit);
    }

    // -----------------------------------------------------------------------
    // 2. Imbalanced transaction must throw
    // -----------------------------------------------------------------------

    public function test_imbalanced_transaction_throws_exception(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Ledger imbalance/');

        $asset   = $this->makeAccount('Cash', 'asset');
        $revenue = $this->makeAccount('Revenue', 'revenue');

        $this->ledger->record('Imbalanced', [
            ['account_id' => $asset->id,   'debit' => 500.00, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0,      'credit' => 999.00], // mismatch
        ]);
    }

    // -----------------------------------------------------------------------
    // 3. Single-entry transaction must throw
    // -----------------------------------------------------------------------

    public function test_single_entry_throws_exception(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/at least 2 entries/');

        $asset = $this->makeAccount('Cash', 'asset');

        $this->ledger->record('Single entry', [
            ['account_id' => $asset->id, 'debit' => 500.00, 'credit' => 0],
        ]);
    }

    // -----------------------------------------------------------------------
    // 4. Settlement flow creates correct entries
    // -----------------------------------------------------------------------

    public function test_settlement_flow_creates_balanced_entries(): void
    {
        $txn = $this->ledger->recordSettlement(
            driverId:       1,
            totalAmount:    10000.00,
            commission:     800.00,
            driverPayout:   9200.00,
            referenceType:  'payout',
            referenceId:    1,
        );

        $this->assertTrue($txn->isBalanced());

        $entries = $txn->entries()->get();
        $this->assertCount(3, $entries);

        $totalDebit  = $entries->sum('debit');
        $totalCredit = $entries->sum('credit');
        $this->assertEqualsWithDelta(10000.00, $totalDebit, 0.01);
        $this->assertEqualsWithDelta(10000.00, $totalCredit, 0.01);
    }

    // -----------------------------------------------------------------------
    // 5. Ledger entry is immutable (PHP layer)
    // -----------------------------------------------------------------------

    public function test_ledger_entry_cannot_be_updated_via_model(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/immutable/');

        $asset   = $this->makeAccount('Cash', 'asset');
        $revenue = $this->makeAccount('Revenue', 'revenue');

        $txn = $this->ledger->record('Immutable test', [
            ['account_id' => $asset->id,   'debit' => 100.00, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0,      'credit' => 100.00],
        ]);

        $entry = $txn->entries()->first();
        $entry->update(['debit' => 999.00]); // must throw
    }

    // -----------------------------------------------------------------------
    // 6. Ledger entry cannot be deleted via model
    // -----------------------------------------------------------------------

    public function test_ledger_entry_cannot_be_deleted_via_model(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/immutable/');

        $asset   = $this->makeAccount('Cash', 'asset');
        $revenue = $this->makeAccount('Revenue', 'revenue');

        $txn = $this->ledger->record('Delete test', [
            ['account_id' => $asset->id,   'debit' => 50.00, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0,     'credit' => 50.00],
        ]);

        $entry = $txn->entries()->first();
        $entry->delete(); // must throw
    }

    // -----------------------------------------------------------------------
    // 7. Refund flow reverses correctly
    // -----------------------------------------------------------------------

    public function test_refund_flow_balances(): void
    {
        $user    = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id]);
        $payment = Payment::factory()->create([
            'booking_id'      => $booking->id,
            'user_id'         => $user->id,
            'amount'          => 5000.00,
            'driver_amount'   => 4600.00,
            'platform_fee'    => 400.00,
            'payment_provider'=> 'stripe',
            'status'          => 'COMPLETED',
        ]);

        $txn = $this->ledger->recordRefund($payment);

        $this->assertTrue($txn->isBalanced());
        $this->assertEqualsWithDelta(5000.00, $txn->total_debit, 0.01);
        $this->assertEqualsWithDelta(5000.00, $txn->total_credit, 0.01);
    }

    // -----------------------------------------------------------------------
    // 8. Account balances reflect the correct normal balance convention
    // -----------------------------------------------------------------------

    public function test_asset_account_balance_is_debit_minus_credit(): void
    {
        $debitAcct  = $this->makeAccount('Clearing', 'asset');
        $creditAcct = $this->makeAccount('Escrow', 'liability');

        $this->ledger->record('Balance test', [
            ['account_id' => $debitAcct->id,  'debit' => 2500.00, 'credit' => 0],
            ['account_id' => $creditAcct->id, 'debit' => 0,       'credit' => 2500.00],
        ]);

        $assetBalance     = $this->ledger->getAccountBalance($debitAcct->fresh());
        $liabilityBalance = $this->ledger->getAccountBalance($creditAcct->fresh());

        $this->assertEqualsWithDelta(2500.00, $assetBalance, 0.01);
        $this->assertEqualsWithDelta(2500.00, $liabilityBalance, 0.01);
    }

    // -----------------------------------------------------------------------
    // 9. UUID is auto-generated and unique per transaction
    // -----------------------------------------------------------------------

    public function test_each_transaction_gets_a_unique_uuid(): void
    {
        $a = $this->makeAccount('A', 'asset');
        $b = $this->makeAccount('B', 'liability');

        $t1 = $this->ledger->record('T1', [
            ['account_id' => $a->id, 'debit' => 1, 'credit' => 0],
            ['account_id' => $b->id, 'debit' => 0, 'credit' => 1],
        ]);
        $t2 = $this->ledger->record('T2', [
            ['account_id' => $a->id, 'debit' => 1, 'credit' => 0],
            ['account_id' => $b->id, 'debit' => 0, 'credit' => 1],
        ]);

        $this->assertNotEquals($t1->uuid, $t2->uuid);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function makeAccount(string $name, string $type): LedgerAccount
    {
        return LedgerAccount::create([
            'name'       => $name . '_' . uniqid(),
            'type'       => $type,
            'owner_type' => 'platform',
            'owner_id'   => null,
            'currency'   => 'RWF',
            'is_active'  => true,
        ]);
    }
}
