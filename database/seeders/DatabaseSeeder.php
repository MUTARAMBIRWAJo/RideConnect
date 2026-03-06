<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\MobileUser;
use App\Models\Manager;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Truncate all tables in the correct order (respecting foreign keys)
        // --- Fintech architecture tables (must come before core tables) ---
        DB::statement('TRUNCATE TABLE ledger_entries CASCADE');
        DB::statement('TRUNCATE TABLE ledger_transactions CASCADE');
        DB::statement('TRUNCATE TABLE ledger_accounts CASCADE');
        DB::statement('TRUNCATE TABLE fraud_flags CASCADE');
        DB::statement('TRUNCATE TABLE platform_commissions CASCADE');
        DB::statement('TRUNCATE TABLE driver_payouts CASCADE');
        // --- Core tables ---
        DB::statement('TRUNCATE TABLE activity_logs CASCADE');
        DB::statement('TRUNCATE TABLE tickets CASCADE');
        DB::statement('TRUNCATE TABLE driver_earnings CASCADE');
        DB::statement('TRUNCATE TABLE payments_v2 CASCADE');
        DB::statement('TRUNCATE TABLE trips CASCADE');
        DB::statement('TRUNCATE TABLE vehicles_v2 CASCADE');
        DB::statement('TRUNCATE TABLE reviews CASCADE');
        DB::statement('TRUNCATE TABLE payments CASCADE');
        DB::statement('TRUNCATE TABLE bookings CASCADE');
        DB::statement('TRUNCATE TABLE rides CASCADE');
        DB::statement('TRUNCATE TABLE vehicles CASCADE');
        DB::statement('TRUNCATE TABLE drivers CASCADE');
        DB::statement('TRUNCATE TABLE notifications CASCADE');
        DB::statement('TRUNCATE TABLE mobile_users CASCADE');
        DB::statement('TRUNCATE TABLE managers CASCADE');
        DB::statement('TRUNCATE TABLE users CASCADE');

        // Reset all sequences
        DB::statement('ALTER SEQUENCE users_id_seq RESTART WITH 1');
        DB::statement('ALTER SEQUENCE drivers_id_seq RESTART WITH 1');
        DB::statement('ALTER SEQUENCE vehicles_id_seq RESTART WITH 1');
        DB::statement('ALTER SEQUENCE rides_id_seq RESTART WITH 1');
        DB::statement('ALTER SEQUENCE bookings_id_seq RESTART WITH 1');
        DB::statement('ALTER SEQUENCE payments_id_seq RESTART WITH 1');
        DB::statement('ALTER SEQUENCE reviews_id_seq RESTART WITH 1');
        DB::statement('ALTER SEQUENCE notifications_id_seq RESTART WITH 1');
        DB::statement('ALTER SEQUENCE mobile_users_id_seq RESTART WITH 1');
        DB::statement('ALTER SEQUENCE managers_id_seq RESTART WITH 1');
        DB::statement('ALTER SEQUENCE vehicles_v2_id_seq RESTART WITH 1');
        DB::statement('ALTER SEQUENCE trips_id_seq RESTART WITH 1');
        DB::statement('ALTER SEQUENCE payments_v2_id_seq RESTART WITH 1');
        DB::statement('ALTER SEQUENCE driver_earnings_id_seq RESTART WITH 1');
        DB::statement('ALTER SEQUENCE tickets_id_seq RESTART WITH 1');
        DB::statement('ALTER SEQUENCE activity_logs_id_seq RESTART WITH 1');
        // Fintech architecture sequences
        DB::statement('ALTER SEQUENCE ledger_accounts_id_seq RESTART WITH 1');
        DB::statement('ALTER SEQUENCE ledger_entries_id_seq RESTART WITH 1');
        DB::statement('ALTER SEQUENCE fraud_flags_id_seq RESTART WITH 1');
        DB::statement('ALTER SEQUENCE platform_commissions_id_seq RESTART WITH 1');
        DB::statement('ALTER SEQUENCE driver_payouts_id_seq RESTART WITH 1');

        // Run seeders for mobile_users and managers tables FIRST (source of truth)
        $this->call([
            RoleSeeder::class,
            MobileUserSeeder::class,
            ManagerSeeder::class,
        ]);

        // Sync to Users table NOW so DriverSeeder can find the users
        $this->syncMobileUsersToUsers();
        $this->syncManagersToUsers();

        // Run seeders that depend on having users in the users table
        $this->call([
            DriverSeeder::class,
            VehicleSeeder::class,
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
        ]);

        // Fintech architecture seeders (depend on drivers, rides, payments, users)
        $this->call([
            LedgerAccountSeeder::class,
            LedgerTransactionSeeder::class,
            DriverWalletSeeder::class,
            DriverPayoutSeeder::class,
            FraudFlagSeeder::class,
        ]);
    }

    /**
     * Sync Managers to Users table
     */
    protected function syncManagersToUsers(): void
    {
        $managers = DB::table('managers')->get();

        foreach ($managers as $manager) {
            DB::table('users')->updateOrInsert(
                ['email' => $manager->email],
                [
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
                ]
            );
        }
    }

    /**
     * Sync MobileUsers to Users table
     */
    protected function syncMobileUsersToUsers(): void
    {
        $mobileUsers = DB::table('mobile_users')->get();

        foreach ($mobileUsers as $mobileUser) {
            DB::table('users')->updateOrInsert(
                ['email' => $mobileUser->email],
                [
                    'name' => $mobileUser->first_name . ' ' . $mobileUser->last_name,
                    'password' => $mobileUser->password,
                    'role' => $mobileUser->role,
                    'mobile_user_id' => $mobileUser->id,
                    'manager_id' => null,
                    'phone' => $mobileUser->phone,
                    'profile_photo' => $mobileUser->profile_photo,
                    'is_verified' => $mobileUser->is_verified,
                    'created_at' => $mobileUser->created_at ?? now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
