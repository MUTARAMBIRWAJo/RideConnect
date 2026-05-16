<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('drivers') && ! Schema::hasColumn('drivers', 'is_test')) {
            Schema::table('drivers', function (Blueprint $table): void {
                $table->boolean('is_test')->default(false)->after('availability_status')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('drivers') && Schema::hasColumn('drivers', 'is_test')) {
            Schema::table('drivers', function (Blueprint $table): void {
                $table->dropColumn('is_test');
            });
        }
    }
};
