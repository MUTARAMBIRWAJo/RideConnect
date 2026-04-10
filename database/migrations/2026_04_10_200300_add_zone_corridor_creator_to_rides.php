<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rides')) {
            return;
        }

        Schema::table('rides', function (Blueprint $table): void {
            if (! Schema::hasColumn('rides', 'zone_id')) {
                $table->foreignId('zone_id')->nullable()->after('driver_id')->constrained('zones')->nullOnDelete();
            }

            if (! Schema::hasColumn('rides', 'corridor_id')) {
                $table->foreignId('corridor_id')->nullable()->after('zone_id')->constrained('corridors')->nullOnDelete();
            }

            if (! Schema::hasColumn('rides', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('corridor_id')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('rides')) {
            return;
        }

        Schema::table('rides', function (Blueprint $table): void {
            if (Schema::hasColumn('rides', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }

            if (Schema::hasColumn('rides', 'corridor_id')) {
                $table->dropConstrainedForeignId('corridor_id');
            }

            if (Schema::hasColumn('rides', 'zone_id')) {
                $table->dropConstrainedForeignId('zone_id');
            }
        });
    }
};
