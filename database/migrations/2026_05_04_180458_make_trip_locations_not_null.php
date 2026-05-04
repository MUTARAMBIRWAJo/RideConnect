<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // For PostgreSQL, use raw SQL
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE trips ALTER COLUMN pickup_location SET NOT NULL');
            DB::statement('ALTER TABLE trips ALTER COLUMN dropoff_location SET NOT NULL');
            DB::statement('ALTER TABLE trips ALTER COLUMN pickup_lat SET NOT NULL');
            DB::statement('ALTER TABLE trips ALTER COLUMN pickup_lng SET NOT NULL');
            DB::statement('ALTER TABLE trips ALTER COLUMN dropoff_lat SET NOT NULL');
            DB::statement('ALTER TABLE trips ALTER COLUMN dropoff_lng SET NOT NULL');
            return;
        }

        // For SQLite, ALTER TABLE changes not directly supported
        if ($driver === 'sqlite') {
            // SQLite requires recreating the table to change constraints
            // For now, we'll rely on application-level validation
            return;
        }

        // For MySQL, use standard Schema builder
        Schema::table('trips', function (Blueprint $table) {
            $table->text('pickup_location')->nullable(false)->change();
            $table->text('dropoff_location')->nullable(false)->change();
            $table->decimal('pickup_lat', 10, 7)->nullable(false)->change();
            $table->decimal('pickup_lng', 10, 7)->nullable(false)->change();
            $table->decimal('dropoff_lat', 10, 7)->nullable(false)->change();
            $table->decimal('dropoff_lng', 10, 7)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE trips ALTER COLUMN pickup_location DROP NOT NULL');
            DB::statement('ALTER TABLE trips ALTER COLUMN dropoff_location DROP NOT NULL');
            DB::statement('ALTER TABLE trips ALTER COLUMN pickup_lat DROP NOT NULL');
            DB::statement('ALTER TABLE trips ALTER COLUMN pickup_lng DROP NOT NULL');
            DB::statement('ALTER TABLE trips ALTER COLUMN dropoff_lat DROP NOT NULL');
            DB::statement('ALTER TABLE trips ALTER COLUMN dropoff_lng DROP NOT NULL');
            return;
        }

        if ($driver === 'sqlite') {
            // SQLite - no changes needed
            return;
        }

        Schema::table('trips', function (Blueprint $table) {
            $table->text('pickup_location')->nullable()->change();
            $table->text('dropoff_location')->nullable()->change();
            $table->decimal('pickup_lat', 10, 7)->nullable()->change();
            $table->decimal('pickup_lng', 10, 7)->nullable()->change();
            $table->decimal('dropoff_lat', 10, 7)->nullable()->change();
            $table->decimal('dropoff_lng', 10, 7)->nullable()->change();
        });
    }
};
