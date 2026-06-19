<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasStarted = Schema::hasColumn('trips_v3', 'trip_started_at');
        $hasCompleted = Schema::hasColumn('trips_v3', 'trip_completed_at');
        $hasMethod = Schema::hasColumn('trips_v3', 'payment_method');
        $hasRef = Schema::hasColumn('trips_v3', 'payment_reference');
        $hasAmount = Schema::hasColumn('trips_v3', 'amount_paid');
        $hasPaidAt = Schema::hasColumn('trips_v3', 'paid_at');
        $hasRating = Schema::hasColumn('trips_v3', 'rating');
        $hasComment = Schema::hasColumn('trips_v3', 'rating_comment');
        $hasRatedAt = Schema::hasColumn('trips_v3', 'rated_at');

        if (!$hasStarted || !$hasCompleted || !$hasMethod || !$hasRef || !$hasAmount || !$hasPaidAt || !$hasRating || !$hasComment || !$hasRatedAt) {
            Schema::table('trips_v3', function (Blueprint $table) use (
                $hasStarted, $hasCompleted, $hasMethod, $hasRef, $hasAmount, $hasPaidAt, $hasRating, $hasComment, $hasRatedAt
            ): void {
                if (!$hasStarted) {
                    $table->timestamp('trip_started_at')->nullable();
                }
                if (!$hasCompleted) {
                    $table->timestamp('trip_completed_at')->nullable();
                }
                if (!$hasMethod) {
                    $table->string('payment_method')->nullable();
                }
                if (!$hasRef) {
                    $table->string('payment_reference')->nullable();
                }
                if (!$hasAmount) {
                    $table->decimal('amount_paid', 10, 2)->nullable();
                }
                if (!$hasPaidAt) {
                    $table->timestamp('paid_at')->nullable();
                }
                if (!$hasRating) {
                    $table->unsignedTinyInteger('rating')->nullable();
                }
                if (!$hasComment) {
                    $table->text('rating_comment')->nullable();
                }
                if (!$hasRatedAt) {
                    $table->timestamp('rated_at')->nullable();
                }
            });
        }
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
