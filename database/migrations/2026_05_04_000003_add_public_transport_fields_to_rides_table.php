<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table): void {
            if (! Schema::hasColumn('rides', 'route_id')) {
                $table->foreignId('route_id')->nullable()->after('corridor_id')->constrained('routes')->nullOnDelete();
            }

            if (! Schema::hasColumn('rides', 'bus_number')) {
                $table->string('bus_number')->nullable()->after('route_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table): void {
            if (Schema::hasColumn('rides', 'bus_number')) {
                $table->dropColumn('bus_number');
            }

            if (Schema::hasColumn('rides', 'route_id')) {
                $table->dropConstrainedForeignId('route_id');
            }
        });
    }
};