<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('corridor_id')->constrained('corridors')->cascadeOnDelete();
            $table->string('route_code');
            $table->string('name');
            $table->string('via')->nullable();
            $table->string('origin');
            $table->string('destination');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['corridor_id', 'route_code']);
            $table->index(['corridor_id', 'is_active']);
        });

        Schema::create('route_stops', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('route_id')->constrained('routes')->cascadeOnDelete();
            $table->string('stop_name');
            $table->unsignedInteger('stop_order');
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lng', 11, 8)->nullable();
            $table->timestamps();

            $table->index(['route_id', 'stop_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_stops');
        Schema::dropIfExists('routes');
    }
};
