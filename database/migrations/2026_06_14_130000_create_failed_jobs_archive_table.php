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
        Schema::create('failed_jobs_archive', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
            $table->timestamp('archived_at')->useCurrent();
            $table->string('category')->nullable();
            $table->text('notes')->nullable();

            $table->index(['category', 'archived_at']);
        });
            } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Migration 2026_06_14_130000_create_failed_jobs_archive_table.php skipped: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_jobs_archive');
    }
};
