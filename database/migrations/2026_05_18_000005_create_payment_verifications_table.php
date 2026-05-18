<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_verifications')) {
            return;
        }

        Schema::create('payment_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('verification_method', 64);
            $table->string('status', 32);
            $table->text('notes')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['payment_id', 'status']);
            $table->index(['verified_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_verifications');
    }
};
