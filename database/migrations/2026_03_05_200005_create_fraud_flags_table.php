<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fraud_flags', function (Blueprint $table) {
            $table->id();
            // driver | passenger | transaction
            $table->string('entity_type', 30);
            $table->unsignedBigInteger('entity_id');
            $table->string('reason');
            // low | medium | high
            $table->string('severity', 10)->default('medium');
            $table->boolean('resolved')->default(false);
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['severity', 'resolved']);
            $table->index('resolved');

            $table->foreign('resolved_by')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_flags');
    }
};
