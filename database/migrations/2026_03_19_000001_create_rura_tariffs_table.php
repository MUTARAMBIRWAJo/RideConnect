<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rura_tariffs', function (Blueprint $table) {
            $table->id();
            $table->string('route_code');
            $table->string('corridor');
            $table->string('origin_stop');
            $table->string('destination_stop');
            $table->integer('fare_rwf');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rura_tariffs');
    }
};
