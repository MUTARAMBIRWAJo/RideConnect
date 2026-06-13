<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Normalize passenger_id foreign keys from legacy mobile_users.id to canonical users.id.
     */
    public function up(): void
    {
        $this->backfillPassengerIds('trips');
        $this->backfillPassengerIds('matching_sessions');
        $this->backfillPassengerIds('seat_reservations');
        $this->backfillPassengerIds('transport_tickets');
        $this->backfillPassengerIds('passenger_route_boardings');

        if (Schema::hasTable('ride_cancellations') && Schema::hasColumn('ride_cancellations', 'passenger_id')) {
            $this->backfillPassengerIds('ride_cancellations');
        }

        if (Schema::hasTable('passenger_boarding_events') && Schema::hasColumn('passenger_boarding_events', 'passenger_id')) {
            $this->backfillPassengerIds('passenger_boarding_events');
        }

        $this->replacePassengerForeignKey('trips');
        $this->replacePassengerForeignKey('matching_sessions');
        $this->replacePassengerForeignKey('seat_reservations');
        $this->replacePassengerForeignKey('transport_tickets');
        $this->replacePassengerForeignKey('passenger_route_boardings');

        if (Schema::hasTable('ride_cancellations') && Schema::hasColumn('ride_cancellations', 'passenger_id')) {
            $this->replacePassengerForeignKey('ride_cancellations');
        }

        if (Schema::hasTable('passenger_boarding_events') && Schema::hasColumn('passenger_boarding_events', 'passenger_id')) {
            $this->replacePassengerForeignKey('passenger_boarding_events');
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive: reverting would reintroduce dual identity paths.
    }

    private function backfillPassengerIds(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'passenger_id')) {
            return;
        }

        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'mobile_user_id')) {
            return;
        }

        $driverName = Schema::getConnection()->getDriverName();

        if ($driverName === 'pgsql') {
            DB::statement("
                UPDATE {$table} AS target
                SET passenger_id = u.id
                FROM users AS u
                WHERE u.mobile_user_id = target.passenger_id
                  AND target.passenger_id <> u.id
            ");

            return;
        }

        if ($driverName === 'mysql') {
            DB::statement("
                UPDATE {$table} AS target
                INNER JOIN users AS u ON u.mobile_user_id = target.passenger_id
                SET target.passenger_id = u.id
                WHERE target.passenger_id <> u.id
            ");

            return;
        }

        $rows = DB::table($table)
            ->select("{$table}.id", 'users.id as user_id')
            ->join('users', 'users.mobile_user_id', '=', "{$table}.passenger_id")
            ->whereColumn("{$table}.passenger_id", '!=', 'users.id')
            ->get();

        foreach ($rows as $row) {
            DB::table($table)->where('id', $row->id)->update(['passenger_id' => $row->user_id]);
        }
    }

    private function replacePassengerForeignKey(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'passenger_id')) {
            return;
        }

        $driverName = Schema::getConnection()->getDriverName();

        if ($driverName === 'sqlite') {
            return;
        }

        $constraint = "{$table}_passenger_id_foreign";

        if ($driverName === 'pgsql') {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
        } elseif ($driverName === 'mysql') {
            try {
                DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$constraint}");
            } catch (\Throwable) {
                // Constraint name may differ on legacy databases.
            }
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            $foreign = $blueprint->foreign('passenger_id')->references('id')->on('users');

            if (in_array($table, ['seat_reservations', 'transport_tickets'], true)) {
                $foreign->nullOnDelete();
            } else {
                $foreign->cascadeOnDelete();
            }
        });
    }
};
