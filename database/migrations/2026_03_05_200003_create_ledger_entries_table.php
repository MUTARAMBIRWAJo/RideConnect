<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('transaction_id');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            // ride | payout | refund | adjustment | webhook
            $table->string('reference_type', 30)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description')->nullable();
            // Immutable — only created_at, no updated_at
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('account_id')
                ->references('id')->on('ledger_accounts')
                ->restrictOnDelete();
            $table->foreign('transaction_id')
                ->references('id')->on('ledger_transactions')
                ->restrictOnDelete();

            $table->index(['account_id', 'created_at']);
            $table->index(['transaction_id']);
            $table->index(['reference_type', 'reference_id']);
        });

        // PostgreSQL check constraints to enforce non-negative amounts
        DB::statement('ALTER TABLE ledger_entries ADD CONSTRAINT chk_ledger_debit_nn CHECK (debit >= 0)');
        DB::statement('ALTER TABLE ledger_entries ADD CONSTRAINT chk_ledger_credit_nn CHECK (credit >= 0)');
        DB::statement('ALTER TABLE ledger_entries ADD CONSTRAINT chk_ledger_nonzero CHECK (debit > 0 OR credit > 0)');

        // PostgreSQL triggers to enforce immutability
        DB::statement("
            CREATE OR REPLACE FUNCTION prevent_ledger_entry_modification()
            RETURNS trigger AS \$\$
            BEGIN
                RAISE EXCEPTION 'Ledger entries are immutable and cannot be modified or deleted';
            END;
            \$\$ LANGUAGE plpgsql
        ");

        DB::statement("
            CREATE TRIGGER trg_ledger_entry_no_update
            BEFORE UPDATE ON ledger_entries
            FOR EACH ROW EXECUTE FUNCTION prevent_ledger_entry_modification()
        ");

        DB::statement("
            CREATE TRIGGER trg_ledger_entry_no_delete
            BEFORE DELETE ON ledger_entries
            FOR EACH ROW EXECUTE FUNCTION prevent_ledger_entry_modification()
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS trg_ledger_entry_no_update ON ledger_entries');
        DB::statement('DROP TRIGGER IF EXISTS trg_ledger_entry_no_delete ON ledger_entries');
        DB::statement('DROP FUNCTION IF EXISTS prevent_ledger_entry_modification()');
        Schema::dropIfExists('ledger_entries');
    }
};
