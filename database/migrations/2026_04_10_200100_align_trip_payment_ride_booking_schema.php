<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE trips ADD COLUMN IF NOT EXISTS booking_id BIGINT NULL');
            DB::statement('ALTER TABLE trips ADD COLUMN IF NOT EXISTS ride_id BIGINT NULL');
            DB::statement('ALTER TABLE trips ADD COLUMN IF NOT EXISTS actual_fare NUMERIC(10,2) NULL');
            DB::statement('ALTER TABLE trips ADD COLUMN IF NOT EXISTS started_at TIMESTAMP NULL');
            DB::statement('ALTER TABLE trips ADD COLUMN IF NOT EXISTS completed_at TIMESTAMP NULL');

            DB::statement("DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'trips_booking_id_foreign') THEN ALTER TABLE trips ADD CONSTRAINT trips_booking_id_foreign FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL; END IF; END $$;");
            DB::statement("DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'trips_ride_id_foreign') THEN ALTER TABLE trips ADD CONSTRAINT trips_ride_id_foreign FOREIGN KEY (ride_id) REFERENCES rides(id) ON DELETE SET NULL; END IF; END $$;");

            DB::statement('ALTER TABLE payments ADD COLUMN IF NOT EXISTS trip_id BIGINT NULL');
            DB::statement('ALTER TABLE payments ADD COLUMN IF NOT EXISTS type VARCHAR(30) NULL');
            DB::statement('ALTER TABLE payments ADD COLUMN IF NOT EXISTS metadata JSONB NULL');
            DB::statement('CREATE INDEX IF NOT EXISTS payments_trip_id_index ON payments (trip_id)');
            DB::statement("DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'payments_trip_id_foreign') THEN ALTER TABLE payments ADD CONSTRAINT payments_trip_id_foreign FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE SET NULL; END IF; END $$;");

            DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_status_check');
            DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_status_check CHECK (status IN ('PENDING','CONFIRMED','CANCELLED','COMPLETED','NO_SHOW','pending','confirmed','cancelled','completed','no_show'))");

            DB::statement('ALTER TABLE rides DROP CONSTRAINT IF EXISTS rides_status_check');
            DB::statement("ALTER TABLE rides ADD CONSTRAINT rides_status_check CHECK (status IN ('DRAFT','PUBLISHED','IN_PROGRESS','COMPLETED','CANCELLED','available','active','scheduled','in_progress','started','completed','cancelled'))");

            DB::statement("UPDATE rides SET status = CASE WHEN LOWER(status) IN ('available','active','scheduled') THEN 'PUBLISHED' WHEN LOWER(status) IN ('in_progress','started') THEN 'IN_PROGRESS' WHEN LOWER(status) = 'completed' THEN 'COMPLETED' WHEN LOWER(status) = 'cancelled' THEN 'CANCELLED' WHEN status IS NULL THEN 'DRAFT' ELSE status END");
            DB::statement('UPDATE payments p SET trip_id = t.id FROM trips t WHERE p.trip_id IS NULL AND p.booking_id IS NOT NULL AND t.booking_id = p.booking_id');

            return;
        }

        if (Schema::hasTable('trips')) {
            Schema::table('trips', function (Blueprint $table): void {
                if (! Schema::hasColumn('trips', 'booking_id')) {
                    $table->foreignId('booking_id')->nullable()->after('ride_id')->constrained('bookings')->nullOnDelete();
                }

                if (! Schema::hasColumn('trips', 'ride_id')) {
                    $table->foreignId('ride_id')->nullable()->after('id')->constrained('rides')->nullOnDelete();
                }

                if (! Schema::hasColumn('trips', 'actual_fare')) {
                    $table->decimal('actual_fare', 10, 2)->nullable()->after('actual_distance');
                }

                if (! Schema::hasColumn('trips', 'started_at')) {
                    $table->timestamp('started_at')->nullable()->after('accepted_at');
                }

                if (! Schema::hasColumn('trips', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable()->after('started_at');
                }
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table): void {
                if (! Schema::hasColumn('payments', 'trip_id')) {
                    $table->foreignId('trip_id')->nullable()->after('id')->constrained('trips')->nullOnDelete();
                }

                if (! Schema::hasColumn('payments', 'type')) {
                    $table->string('type', 30)->nullable()->after('user_id');
                }

                if (! Schema::hasColumn('payments', 'metadata')) {
                    $table->json('metadata')->nullable()->after('status');
                }
            });
        }

        if (Schema::hasTable('bookings')) {
            DB::table('bookings')->whereRaw("LOWER(status) = 'pending'")->update(['status' => 'PENDING']);
            DB::table('bookings')->whereRaw("LOWER(status) = 'confirmed'")->update(['status' => 'CONFIRMED']);
            DB::table('bookings')->whereRaw("LOWER(status) = 'cancelled'")->update(['status' => 'CANCELLED']);
            DB::table('bookings')->whereRaw("LOWER(status) = 'completed'")->update(['status' => 'COMPLETED']);
            DB::table('bookings')->whereRaw("LOWER(status) = 'no_show'")->update(['status' => 'NO_SHOW']);
        }

        if (Schema::hasTable('rides')) {
            DB::table('rides')->whereRaw("LOWER(status) IN ('available','active','scheduled')")->update(['status' => 'PUBLISHED']);
            DB::table('rides')->whereRaw("LOWER(status) IN ('in_progress','started')")->update(['status' => 'IN_PROGRESS']);
            DB::table('rides')->whereRaw("LOWER(status) = 'completed'")->update(['status' => 'COMPLETED']);
            DB::table('rides')->whereRaw("LOWER(status) = 'cancelled'")->update(['status' => 'CANCELLED']);
            DB::table('rides')->whereNull('status')->update(['status' => 'DRAFT']);
        }

        if (Schema::hasTable('payments') && Schema::hasTable('trips') && Schema::hasColumn('payments', 'trip_id') && Schema::hasColumn('payments', 'booking_id') && Schema::hasColumn('trips', 'booking_id')) {
            DB::statement('UPDATE payments SET trip_id = (SELECT id FROM trips WHERE trips.booking_id = payments.booking_id LIMIT 1) WHERE trip_id IS NULL AND booking_id IS NOT NULL');
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_trip_id_foreign');
            DB::statement('DROP INDEX IF EXISTS payments_trip_id_index');
            DB::statement('ALTER TABLE payments DROP COLUMN IF EXISTS trip_id');
            DB::statement('ALTER TABLE payments DROP COLUMN IF EXISTS type');
            DB::statement('ALTER TABLE payments DROP COLUMN IF EXISTS metadata');

            DB::statement('ALTER TABLE trips DROP CONSTRAINT IF EXISTS trips_booking_id_foreign');
            DB::statement('ALTER TABLE trips DROP COLUMN IF EXISTS booking_id');

            return;
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table): void {
                if (Schema::hasColumn('payments', 'trip_id')) {
                    $table->dropConstrainedForeignId('trip_id');
                }

                if (Schema::hasColumn('payments', 'type')) {
                    $table->dropColumn('type');
                }

                if (Schema::hasColumn('payments', 'metadata')) {
                    $table->dropColumn('metadata');
                }
            });
        }

        if (Schema::hasTable('trips')) {
            Schema::table('trips', function (Blueprint $table): void {
                if (Schema::hasColumn('trips', 'booking_id')) {
                    $table->dropConstrainedForeignId('booking_id');
                }
            });
        }
    }
};
