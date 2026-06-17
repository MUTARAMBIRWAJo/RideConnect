<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable'); // tokenable_type, tokenable_id index
            $table->text('fcm_token');
            $table->string('device_type')->default('android'); // android, ios, web
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
            } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Migration 2026_06_16_000001_create_device_tokens_table.php skipped: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
