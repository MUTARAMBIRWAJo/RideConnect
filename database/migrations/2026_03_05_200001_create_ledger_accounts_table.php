<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // asset | liability | revenue | expense
            $table->string('type', 20);
            // platform | driver | passenger
            $table->string('owner_type', 30);
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->string('currency', 10)->default('RWF');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Each account is uniquely identified by name + owner
            $table->unique(['name', 'owner_type', 'owner_id']);
            $table->index(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_accounts');
    }
};
