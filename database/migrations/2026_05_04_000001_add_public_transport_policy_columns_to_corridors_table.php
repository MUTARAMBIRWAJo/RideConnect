<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corridors', function (Blueprint $table): void {
            if (! Schema::hasColumn('corridors', 'code')) {
                $table->string('code')->nullable()->unique()->after('id');
            }

            if (! Schema::hasColumn('corridors', 'kinyarwanda_name')) {
                $table->string('kinyarwanda_name')->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('corridors', function (Blueprint $table): void {
            if (Schema::hasColumn('corridors', 'kinyarwanda_name')) {
                $table->dropColumn('kinyarwanda_name');
            }

            if (Schema::hasColumn('corridors', 'code')) {
                $table->dropUnique(['code']);
                $table->dropColumn('code');
            }
        });
    }
};