<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('location_histories')) {
            Schema::create('location_histories', function (Blueprint $table) {
                $table->id();
                
                // User reference (User or Driver)
                $table->unsignedBigInteger('user_id');
                $table->string('role'); // 'driver' or 'passenger'
                $table->unsignedBigInteger('trip_id')->nullable();
                
                // Location details
                $table->decimal('latitude', 10, 8);
                $table->decimal('longitude', 11, 8);
                
                // Movement details
                $table->decimal('speed', 10, 2)->nullable();
                $table->integer('heading')->nullable();
                
                // Timestamp
                $table->timestamp('created_at')->useCurrent();
                
                // Foreign keys & Indexes for performance
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->index(['user_id', 'created_at']);
                $table->index('trip_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('location_histories');
    }
};
