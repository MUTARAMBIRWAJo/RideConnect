<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_type', 100); // daily_ride_summary, driver_earnings, etc.
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->enum('format', ['csv', 'pdf', 'json'])->default('csv');
            $table->enum('status', ['pending', 'generating', 'ready', 'failed'])->default('pending');
            $table->jsonb('summary_data')->nullable();  // cached report totals
            $table->jsonb('metadata')->nullable();       // filters, options used
            $table->text('error_message')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['report_type', 'period_start', 'period_end']);
            $table->index(['status', 'generated_by']);
            $table->index('generated_at');

            $table->foreign('generated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_reports');
    }
};
