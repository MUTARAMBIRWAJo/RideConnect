<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_wallets', function (Blueprint $table) {
            // Escrow-style wallet balances
            $table->decimal('available_balance', 15, 2)->default(0)->after('current_balance');
            $table->decimal('pending_balance', 15, 2)->default(0)->after('available_balance');
            $table->decimal('frozen_balance', 15, 2)->default(0)->after('pending_balance');
        });
    }

    public function down(): void
    {
        Schema::table('driver_wallets', function (Blueprint $table) {
            $table->dropColumn(['available_balance', 'pending_balance', 'frozen_balance']);
        });
    }
};
