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
        // Add runtime driver availability/location columns used by Driver APIs.
        Schema::table('drivers', function (Blueprint $table): void {
            if (! Schema::hasColumn('drivers', 'availability_status')) {
                $table->string('availability_status', 20)->default('offline')->after('status');
            }

            if (! Schema::hasColumn('drivers', 'current_latitude')) {
                $table->decimal('current_latitude', 10, 7)->nullable()->after('availability_status');
            }

            if (! Schema::hasColumn('drivers', 'current_longitude')) {
                $table->decimal('current_longitude', 10, 7)->nullable()->after('current_latitude');
            }

            if (! Schema::hasColumn('drivers', 'last_online_at')) {
                $table->timestamp('last_online_at')->nullable()->after('current_longitude');
            }
        });

        // Add trip lifecycle columns used by driver request and earnings APIs.
        Schema::table('trips', function (Blueprint $table): void {
            if (! Schema::hasColumn('trips', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('requested_at');
            }

            if (! Schema::hasColumn('trips', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('accepted_at');
            }

            if (! Schema::hasColumn('trips', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('rejected_at');
            }

            if (! Schema::hasColumn('trips', 'actual_pickup_lat')) {
                $table->decimal('actual_pickup_lat', 10, 7)->nullable()->after('dropoff_lng');
            }

            if (! Schema::hasColumn('trips', 'actual_pickup_lng')) {
                $table->decimal('actual_pickup_lng', 10, 7)->nullable()->after('actual_pickup_lat');
            }

            if (! Schema::hasColumn('trips', 'actual_dropoff_lat')) {
                $table->decimal('actual_dropoff_lat', 10, 7)->nullable()->after('actual_pickup_lng');
            }

            if (! Schema::hasColumn('trips', 'actual_dropoff_lng')) {
                $table->decimal('actual_dropoff_lng', 10, 7)->nullable()->after('actual_dropoff_lat');
            }

            if (! Schema::hasColumn('trips', 'actual_distance')) {
                $table->decimal('actual_distance', 10, 2)->nullable()->after('fare');
            }

            if (! Schema::hasColumn('trips', 'actual_fare')) {
                $table->decimal('actual_fare', 10, 2)->nullable()->after('actual_distance');
            }

            if (! Schema::hasColumn('trips', 'paid_to_driver_at')) {
                $table->timestamp('paid_to_driver_at')->nullable()->after('completed_at');
            }
        });

        $this->alignTripDriverForeignKey();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driverName = DB::connection()->getDriverName();

        // Drop FK to drivers and restore FK to mobile_users as in legacy schema.
        if ($driverName === 'pgsql') {
            DB::statement('ALTER TABLE trips DROP CONSTRAINT IF EXISTS trips_driver_id_foreign');
        } elseif ($driverName === 'mysql') {
            DB::statement('ALTER TABLE trips DROP FOREIGN KEY trips_driver_id_foreign');
        }

        Schema::table('trips', function (Blueprint $table): void {
            $table->foreign('driver_id')->references('id')->on('mobile_users')->nullOnDelete();
        });

        Schema::table('trips', function (Blueprint $table): void {
            $table->dropColumn([
                'accepted_at',
                'rejected_at',
                'rejection_reason',
                'actual_pickup_lat',
                'actual_pickup_lng',
                'actual_dropoff_lat',
                'actual_dropoff_lng',
                'actual_distance',
                'actual_fare',
                'paid_to_driver_at',
            ]);
        });

        Schema::table('drivers', function (Blueprint $table): void {
            $table->dropColumn([
                'availability_status',
                'current_latitude',
                'current_longitude',
                'last_online_at',
            ]);
        });
    }

    private function alignTripDriverForeignKey(): void
    {
        if (! Schema::hasTable('trips') || ! Schema::hasTable('drivers') || ! Schema::hasColumn('trips', 'driver_id')) {
            return;
        }

        // Normalize any legacy trips.driver_id values that point to mobile_users.id into drivers.id.
        $trips = DB::table('trips')
            ->whereNotNull('driver_id')
            ->get(['id', 'driver_id']);

        foreach ($trips as $trip) {
            $driverId = (int) $trip->driver_id;

            $alreadyDriverId = DB::table('drivers')->where('id', $driverId)->exists();
            if ($alreadyDriverId) {
                continue;
            }

            $mappedDriverId = DB::table('users')
                ->join('drivers', 'drivers.user_id', '=', 'users.id')
                ->where('users.mobile_user_id', $driverId)
                ->value('drivers.id');

            DB::table('trips')
                ->where('id', $trip->id)
                ->update(['driver_id' => $mappedDriverId ?: null]);
        }

        // Replace existing FK with drivers.id relation expected by Driver APIs.
        $driverName = DB::connection()->getDriverName();
        if ($driverName === 'pgsql') {
            DB::statement('ALTER TABLE trips DROP CONSTRAINT IF EXISTS trips_driver_id_foreign');
        } elseif ($driverName === 'mysql') {
            DB::statement('ALTER TABLE trips DROP FOREIGN KEY trips_driver_id_foreign');
        }

        Schema::table('trips', function (Blueprint $table): void {
            $table->foreign('driver_id')->references('id')->on('drivers')->nullOnDelete();
        });
    }
};
