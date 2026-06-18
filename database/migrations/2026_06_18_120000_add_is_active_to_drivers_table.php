<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('drivers') || Schema::hasColumn('drivers', 'is_active')) {
            return;
        }

        Schema::table('drivers', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('status')->index();
        });

        DB::table('drivers')->whereNull('is_active')->update(['is_active' => true]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('drivers') || ! Schema::hasColumn('drivers', 'is_active')) {
            return;
        }

        Schema::table('drivers', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });
    }
};
