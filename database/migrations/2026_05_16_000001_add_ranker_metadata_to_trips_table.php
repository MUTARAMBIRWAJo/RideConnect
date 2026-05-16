<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            if (! Schema::hasColumn('trips', 'ranker_score')) {
                $table->decimal('ranker_score', 6, 4)->nullable()->after('driver_id');
            }

            if (! Schema::hasColumn('trips', 'ranker_version')) {
                $table->string('ranker_version', 64)->nullable()->after('ranker_score');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            if (Schema::hasColumn('trips', 'ranker_version')) {
                $table->dropColumn('ranker_version');
            }

            if (Schema::hasColumn('trips', 'ranker_score')) {
                $table->dropColumn('ranker_score');
            }
        });
    }
};
