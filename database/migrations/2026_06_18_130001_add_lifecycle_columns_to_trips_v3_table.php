<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips_v3', function (Blueprint $table): void {
            if (! Schema::hasColumn('trips_v3', 'trip_started_at')) {
                $table->timestamp('trip_started_at')->nullable();
            }
            if (! Schema::hasColumn('trips_v3', 'trip_completed_at')) {
                $table->timestamp('trip_completed_at')->nullable();
            }
            if (! Schema::hasColumn('trips_v3', 'payment_method')) {
                $table->string('payment_method')->nullable();
            }
            if (! Schema::hasColumn('trips_v3', 'payment_reference')) {
                $table->string('payment_reference')->nullable();
            }
            if (! Schema::hasColumn('trips_v3', 'amount_paid')) {
                $table->decimal('amount_paid', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('trips_v3', 'paid_at')) {
                $table->timestamp('paid_at')->nullable();
            }
            if (! Schema::hasColumn('trips_v3', 'rating')) {
                $table->unsignedTinyInteger('rating')->nullable();
            }
            if (! Schema::hasColumn('trips_v3', 'rating_comment')) {
                $table->text('rating_comment')->nullable();
            }
            if (! Schema::hasColumn('trips_v3', 'rated_at')) {
                $table->timestamp('rated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('trips_v3', function (Blueprint $table): void {
            $table->dropColumn([
                'trip_started_at',
                'trip_completed_at',
                'payment_method',
                'payment_reference',
                'amount_paid',
                'paid_at',
                'rating',
                'rating_comment',
                'rated_at',
            ]);
        });
    }
};
