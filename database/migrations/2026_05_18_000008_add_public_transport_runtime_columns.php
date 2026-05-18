<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            if (! Schema::hasColumn('trips', 'payment_status')) {
                $table->string('payment_status', 32)->default('pending')->after('status');
            }
            if (! Schema::hasColumn('trips', 'assignment_status')) {
                $table->string('assignment_status', 32)->default('unassigned')->after('payment_status');
            }
            if (! Schema::hasColumn('trips', 'pickup_verified_at')) {
                $table->timestamp('pickup_verified_at')->nullable()->after('accepted_at');
            }
            if (! Schema::hasColumn('trips', 'admin_completed_by')) {
                $table->foreignId('admin_completed_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('trips', 'admin_completion_reason')) {
                $table->text('admin_completion_reason')->nullable()->after('admin_completed_by');
            }
            if (! Schema::hasColumn('trips', 'current_assignment_attempt_id')) {
                $table->unsignedBigInteger('current_assignment_attempt_id')->nullable()->after('assignment_status');
            }
            if (! Schema::hasColumn('trips', 'transport_type')) {
                $table->string('transport_type', 32)->nullable()->after('ride_id');
            }
        });

        Schema::table('drivers', function (Blueprint $table): void {
            if (! Schema::hasColumn('drivers', 'online_since')) {
                $table->timestamp('online_since')->nullable()->after('last_online_at');
            }
        });

        Schema::table('vehicles', function (Blueprint $table): void {
            if (! Schema::hasColumn('vehicles', 'maintenance_status')) {
                $table->string('maintenance_status', 32)->default('operational')->after('is_active');
            }
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "DO $$
                 BEGIN
                     IF NOT EXISTS (
                         SELECT 1 FROM pg_constraint WHERE conname = 'rides_available_seats_non_negative'
                     ) THEN
                         ALTER TABLE rides
                         ADD CONSTRAINT rides_available_seats_non_negative
                         CHECK (available_seats >= 0) NOT VALID;
                     END IF;
                 END $$;"
            );

            DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check');
            DB::statement(
                "ALTER TABLE payments
                 ADD CONSTRAINT payments_status_check
                 CHECK (status IN ('pending', 'processing', 'paid', 'completed', 'failed', 'refunded')) NOT VALID"
            );

            DB::statement(
                "CREATE UNIQUE INDEX IF NOT EXISTS trips_one_active_moto_trip_per_driver
                 ON trips (driver_id)
                 WHERE transport_type = 'MOTORCYCLE'
                 AND status IN ('ACCEPTED', 'STARTED')
                 AND driver_id IS NOT NULL"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS trips_one_active_moto_trip_per_driver');
            DB::statement('ALTER TABLE rides DROP CONSTRAINT IF EXISTS rides_available_seats_non_negative');
            DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check');
            DB::statement(
                "ALTER TABLE payments
                 ADD CONSTRAINT payments_status_check
                 CHECK (status IN ('pending', 'processing', 'completed', 'failed', 'refunded')) NOT VALID"
            );
        }

        Schema::table('vehicles', function (Blueprint $table): void {
            if (Schema::hasColumn('vehicles', 'maintenance_status')) {
                $table->dropColumn('maintenance_status');
            }
        });

        Schema::table('drivers', function (Blueprint $table): void {
            if (Schema::hasColumn('drivers', 'online_since')) {
                $table->dropColumn('online_since');
            }
        });

        Schema::table('trips', function (Blueprint $table): void {
            foreach ([
                'payment_status',
                'assignment_status',
                'pickup_verified_at',
                'admin_completion_reason',
                'current_assignment_attempt_id',
                'transport_type',
            ] as $column) {
                if (Schema::hasColumn('trips', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('trips', 'admin_completed_by')) {
                $table->dropConstrainedForeignId('admin_completed_by');
            }
        });
    }
};
