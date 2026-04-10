<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('zones')) {
            Schema::create('zones', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('corridors')) {
            Schema::create('corridors', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->foreignId('start_zone_id')->constrained('zones')->cascadeOnDelete();
                $table->foreignId('end_zone_id')->constrained('zones')->cascadeOnDelete();
                $table->decimal('base_fare', 10, 2)->default(0);
                $table->decimal('price_per_km', 10, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('corridors');
        Schema::dropIfExists('zones');
    }
};
