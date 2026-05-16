<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seed_runs')) {
            return;
        }

        Schema::create('seed_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->json('meta')->nullable();
            $table->timestampTz('seeded_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seed_runs');
    }
};
