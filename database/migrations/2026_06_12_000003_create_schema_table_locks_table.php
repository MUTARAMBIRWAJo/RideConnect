<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('schema_table_locks')) {
            return;
        }

        Schema::create('schema_table_locks', function (Blueprint $table): void {
            $table->id();
            $table->string('schema_name', 64)->default('public');
            $table->string('table_name', 128);
            $table->string('qualified_name', 196);
            $table->boolean('is_locked')->default(true);
            $table->string('locked_reason')->nullable();
            $table->timestampTz('locked_at')->useCurrent();
            $table->timestamps();

            $table->unique(['schema_name', 'table_name']);
            $table->index('is_locked');
        });
    }

    public function down(): void
    {
        // Registry table is intentionally retained to preserve lock metadata.
    }
};
