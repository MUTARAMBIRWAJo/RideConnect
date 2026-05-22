<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $resumeMode = (bool) env('DB_SEED_RESUME', false);
        $skipReset = (bool) env('DB_SEED_SKIP_RESET', true); // Default to skip reset to avoid foreign key issues
        $skipRoleSeeder = (bool) env('DB_SEED_SKIP_ROLE_SEEDER', false);
        $skipTopUp = (bool) env('DB_SEED_SKIP_TOP_UP', false);

        // Resume mode keeps already-seeded data and only runs the final top-up pass.
        if ($resumeMode) {
            $this->command?->info('DB_SEED_RESUME enabled: skipping truncation and completed seeders.');
            $resumeSeeders = [
                AIRwandaTrainingSeeder::class,
                RwandaFiftyTopUpSeeder::class,
            ];

            if (! $skipRoleSeeder) {
                array_unshift($resumeSeeders, RoleSeeder::class);
            }

            $this->call($resumeSeeders);

            return;
        }

        // Skip database reset by default (resetDatabaseForSeeding is commented out)
        // Just ensure roles and managers are seeded
        $this->command?->info('Skipping database reset. Seeding roles and managers...');

        // Run seeders for mobile_users and managers tables FIRST (source of truth)
        $baseSeeders = [
            MobileUserSeeder::class,
            ManagerSeeder::class,
        ];

        if (! $skipRoleSeeder) {
            array_unshift($baseSeeders, RoleSeeder::class);
        }

        $this->call($baseSeeders);

        // Sync to Users table NOW so DriverSeeder can find the users
        $this->syncMobileUsersToUsers();
        $this->syncManagersToUsers();

        // Run seeders that depend on having users in the users table
        $this->call([
            DriverSeeder::class,
            DriverLocationSeeder::class,
            VehicleSeeder::class,
            MLTrainingVolumeSeeder::class,
            RideSeeder::class,
            BookingSeeder::class,
            PaymentSeeder::class,
            ReviewSeeder::class,
            NotificationSeeder::class,
        ]);

        // Run remaining seeders
        $this->call([
            VehicleV2Seeder::class,
            TripSeeder::class,
            PaymentV2Seeder::class,
            DriverEarningSeeder::class,
            TicketSeeder::class,
            ActivityLogSeeder::class,
            RuraTariffSeeder::class,
            ZoneCorridorSeeder::class,
            PublicTransportSeeder::class,
            BusCorridorAssignmentSeeder::class,
        ]);

        // Fintech architecture seeders (depend on drivers, rides, payments, users)
        $fintechSeeders = [
            LedgerAccountSeeder::class,
            LedgerTransactionSeeder::class,
            DriverWalletSeeder::class,
            DriverPayoutSeeder::class,
            FraudFlagSeeder::class,
        ];

        if (! $skipTopUp) {
            $fintechSeeders[] = AIRwandaTrainingSeeder::class;
            $fintechSeeders[] = RwandaFiftyTopUpSeeder::class;
        }

        $this->call($fintechSeeders);
    }

    /**
     * Sync Managers to Users table
     */
    protected function syncManagersToUsers(): void
    {
        $managers = DB::table('managers')->get();
        $rowsByEmail = [];
        $managerEmails = $managers->pluck('email')->all();
        $existingIdsByEmail = DB::table('users')
            ->whereIn('email', $managerEmails)
            ->pluck('id', 'email')
            ->all();
        $nextId = (int) (DB::table('users')->max('id') ?? 0);

        foreach ($managers as $manager) {
            $id = $existingIdsByEmail[$manager->email] ?? ++$nextId;

            $rowsByEmail[$manager->email] = [
                'id' => $id,
                'email' => $manager->email,
                'name' => $manager->name,
                'password' => $manager->password,
                'role' => $manager->role,
                'manager_id' => $manager->id,
                'mobile_user_id' => null,
                'phone' => null,
                'profile_photo' => null,
                'is_verified' => true,
                'created_at' => $manager->created_at ?? now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($rowsByEmail)) {
            DB::table('users')->upsert(
                array_values($rowsByEmail),
                ['email'],
                ['name', 'password', 'role', 'manager_id', 'mobile_user_id', 'phone', 'profile_photo', 'is_verified', 'updated_at']
            );

            $this->syncTableIdSequence('users');
        }
    }

    /**
     * Sync MobileUsers to Users table
     */
    protected function syncMobileUsersToUsers(): void
    {
        $mobileUsers = DB::table('mobile_users')->get();
        $rowsByEmail = [];
        $mobileEmails = $mobileUsers->pluck('email')->all();
        $existingIdsByEmail = DB::table('users')
            ->whereIn('email', $mobileEmails)
            ->pluck('id', 'email')
            ->all();
        $nextId = (int) (DB::table('users')->max('id') ?? 0);

        foreach ($mobileUsers as $mobileUser) {
            $id = $existingIdsByEmail[$mobileUser->email] ?? ++$nextId;

            $rowsByEmail[$mobileUser->email] = [
                'id' => $id,
                'email' => $mobileUser->email,
                'name' => $mobileUser->first_name.' '.$mobileUser->last_name,
                'password' => $mobileUser->password,
                'role' => $mobileUser->role,
                'mobile_user_id' => $mobileUser->id,
                'manager_id' => null,
                'phone' => $mobileUser->phone,
                'profile_photo' => $mobileUser->profile_photo,
                'is_verified' => $mobileUser->is_verified,
                'created_at' => $mobileUser->created_at ?? now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($rowsByEmail)) {
            DB::table('users')->upsert(
                array_values($rowsByEmail),
                ['email'],
                ['name', 'password', 'role', 'mobile_user_id', 'manager_id', 'phone', 'profile_photo', 'is_verified', 'updated_at']
            );

            $this->syncTableIdSequence('users');
        }
    }

    // private function resetDatabaseForSeeding(): bool
    // {
    //     $tables = [
    //         'ledger_entries',
    //         'ledger_transactions',
    //         'ledger_accounts',
    //         'fraud_flags',
    //         'platform_commissions',
    //         'driver_payouts',
    //         'model_has_permissions',
    //         'model_has_roles',
    //         'role_has_permissions',
    //         'permissions',
    //         'roles',
    //         'activity_logs',
    //         'tickets',
    //         'driver_earnings',
    //         'payments_v2',
    //         'trips',
    //         'vehicles_v2',
    //         'reviews',
    //         'payments',
    //         'bookings',
    //         'rides',
    //         'vehicles',
    //         'drivers',
    //         'notifications',
    //         'user_notifications',
    //         'mobile_device_tokens',
    //         'driver_locations',
    //         'mobile_users',
    //         'managers',
    //         'users',
    //     ];

    //     try {
    //         foreach ($tables as $table) {
    //             if (!Schema::hasTable($table)) {
    //                 continue;
    //             }

    //             $this->withRetry(fn () => DB::statement("TRUNCATE TABLE {$table} CASCADE"));
    //             $this->restartTableIdSequence($table);
    //         }

    //         return true;
    //     } catch (Throwable $e) {
    //         $this->command?->warn('Reset failure: ' . $e->getMessage());

    //         return false;
    //     }
    // }

    private function withRetry(callable $operation, int $maxAttempts = 3): void
    {
        $attempt = 1;

        beginning:
        try {
            $operation();
        } catch (Throwable $e) {
            if ($attempt >= $maxAttempts) {
                throw $e;
            }

            $attempt++;
            DB::reconnect();
            usleep(250000);
            goto beginning;
        }
    }

    private function restartTableIdSequence(string $table): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::hasColumn($table, 'id')) {
            return;
        }

        $sequence = DB::selectOne("SELECT pg_get_serial_sequence('{$table}', 'id') AS seq");
        $sequenceName = $sequence?->seq ?? null;

        if (! is_string($sequenceName) || $sequenceName === '') {
            return;
        }

        $this->withRetry(fn () => DB::statement("SELECT setval('{$sequenceName}', 1, false)"));
    }

    private function syncTableIdSequence(string $table): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
            return;
        }

        $sequence = DB::selectOne("SELECT pg_get_serial_sequence('{$table}', 'id') AS seq");
        $sequenceName = $sequence?->seq ?? null;

        if (! is_string($sequenceName) || $sequenceName === '') {
            return;
        }

        DB::statement("SELECT setval('{$sequenceName}', COALESCE((SELECT MAX(id) FROM {$table}), 0) + 1, false)");
    }
}
