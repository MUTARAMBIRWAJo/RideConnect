<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $isPostgres = DB::getDriverName() === 'pgsql';

        // 1. Drop foreign key defensively without triggering database exception
        if (Schema::hasTable('passenger_locations') && Schema::hasColumn('passenger_locations', 'passenger_id')) {
            $fkExists = false;

            if ($isPostgres) {
                $constraints = DB::select("
                    SELECT tc.constraint_name 
                    FROM information_schema.table_constraints AS tc 
                    JOIN information_schema.key_column_usage AS kcu
                      ON tc.constraint_name = kcu.constraint_name
                      AND tc.table_schema = kcu.table_schema
                    WHERE tc.constraint_type = 'FOREIGN KEY' 
                      AND tc.table_name = 'passenger_locations' 
                      AND kcu.column_name = 'passenger_id'
                ");
                if (!empty($constraints)) {
                    $fkExists = true;
                    $fkName = $constraints[0]->constraint_name;
                }
            } else {
                $fkExists = true;
                $fkName = 'passenger_locations_passenger_id_foreign';
            }

            if ($fkExists) {
                try {
                    Schema::table('passenger_locations', function (Blueprint $table) use ($fkName) {
                        $table->dropForeign($fkName);
                    });
                } catch (\Exception $e) {
                    // Safe fallback
                }
            }
        }

        // 2. Add or rename columns defensively
        if (Schema::hasTable('passenger_locations')) {
            Schema::table('passenger_locations', function (Blueprint $table) {
                if (Schema::hasColumn('passenger_locations', 'passenger_id') && !Schema::hasColumn('passenger_locations', 'user_id')) {
                    $table->renameColumn('passenger_id', 'user_id');
                } elseif (!Schema::hasColumn('passenger_locations', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable();
                }
            });

            // Ensure user_id unique index and foreign key exists
            $isSqlite = DB::getDriverName() === 'sqlite';
            if ($isPostgres) {
                $hasUnique = !empty(DB::select("
                    SELECT indexname FROM pg_indexes 
                    WHERE tablename = 'passenger_locations' AND indexname = 'passenger_locations_user_id_unique'
                "));
                
                $hasFk = !empty(DB::select("
                    SELECT tc.constraint_name 
                    FROM information_schema.table_constraints AS tc 
                    JOIN information_schema.key_column_usage AS kcu
                      ON tc.constraint_name = kcu.constraint_name
                      AND tc.table_schema = kcu.table_schema
                    WHERE tc.constraint_type = 'FOREIGN KEY' 
                      AND tc.table_name = 'passenger_locations' 
                      AND kcu.column_name = 'user_id'
                "));
            } else {
                $hasUnique = false;
                $hasFk = false;
            }

            Schema::table('passenger_locations', function (Blueprint $table) use ($hasUnique, $hasFk, $isSqlite) {
                if (!$hasUnique && !$isSqlite) {
                    $table->unique('user_id');
                }
                if (!$hasFk && !$isSqlite) {
                    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                }
            });

            Schema::table('passenger_locations', function (Blueprint $table) {
                if (!Schema::hasColumn('passenger_locations', 'trip_id')) {
                    $table->unsignedBigInteger('trip_id')->nullable()->after('user_id');
                }
                if (!Schema::hasColumn('passenger_locations', 'lat')) {
                    $table->decimal('lat', 10, 8)->nullable()->after('trip_id');
                }
                if (!Schema::hasColumn('passenger_locations', 'lng')) {
                    $table->decimal('lng', 11, 8)->nullable()->after('lat');
                }
                if (!Schema::hasColumn('passenger_locations', 'speed')) {
                    $table->decimal('speed', 10, 2)->nullable()->after('longitude');
                }
                if (!Schema::hasColumn('passenger_locations', 'heading')) {
                    $table->integer('heading')->nullable()->after('speed');
                }
                if (!Schema::hasColumn('passenger_locations', 'accuracy')) {
                    $table->decimal('accuracy', 10, 2)->nullable()->after('heading');
                }
                if (!Schema::hasColumn('passenger_locations', 'is_online')) {
                    $table->boolean('is_online')->default(true)->after('accuracy');
                }
                if (!Schema::hasColumn('passenger_locations', 'recorded_at')) {
                    $table->timestamp('recorded_at')->useCurrent()->after('is_online');
                }
                if (!Schema::hasColumn('passenger_locations', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (!Schema::hasColumn('passenger_locations', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // No rollback needed for defensive changes
    }
};
