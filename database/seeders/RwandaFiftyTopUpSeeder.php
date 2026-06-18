<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RwandaFiftyTopUpSeeder extends Seeder
{
    private const TARGET_ROWS = 50;

    /** @var array<string, array<int, int>> */
    private array $idPools = [];

    /** @var array<string, array<int, string>> */
    private array $columnCache = [];

    /** @var array<string, array<int, int>> */
    private array $usedReferenceIds = [];

    // Reference sources:
    // - https://en.wikipedia.org/wiki/Provinces_of_Rwanda
    // - https://en.wikipedia.org/wiki/Districts_of_Rwanda
    private const RWANDA_DISTRICTS = [
        'Gasabo', 'Kicukiro', 'Nyarugenge', 'Bugesera', 'Gatsibo', 'Kayonza', 'Kirehe', 'Ngoma', 'Nyagatare', 'Rwamagana',
        'Burera', 'Gakenke', 'Gicumbi', 'Musanze', 'Rulindo', 'Gisagara', 'Huye', 'Kamonyi', 'Muhanga', 'Nyamagabe',
        'Nyanza', 'Nyaruguru', 'Ruhango', 'Karongi', 'Ngororero', 'Nyabihu', 'Nyamasheke', 'Rubavu', 'Rusizi', 'Rutsiro',
    ];

    private const RWANDA_HOTSPOTS = [
        'Kigali Convention Centre', 'Kigali International Airport', 'Nyabugogo Bus Terminal', 'Kimironko Market',
        'Amahoro Stadium', 'Kigali City Tower', 'Kacyiru', 'Nyamirambo', 'Remera', 'Huye Town', 'Musanze Town', 'Rubavu Beach',
    ];

    private array $firstNames = [
        'Jean', 'Patrick', 'Claude', 'Eric', 'Alice', 'Grace', 'David', 'Marie', 'Samuel', 'Diane',
        'Emmanuel', 'Claudine', 'Yvette', 'Fabrice', 'Nadine', 'Didier', 'Sandrine', 'Pacifique', 'Aimable', 'Aline',
    ];

    private array $lastNames = [
        'Mugabo', 'Habimana', 'Niyonzima', 'Nsanzimana', 'Uwimana', 'Mukamana', 'Tuyishime', 'Ingabire', 'Bizimungu', 'Muhire',
        'Nshimiyimana', 'Uwase', 'Iradukunda', 'Uwingabire', 'Munyaneza', 'Hakizimana', 'Nkundimana', 'Nyirahabimana',
    ];

    public function run(): void
    {
        $this->topUpMobileUsers(100);
        $this->syncMobileUsersToUsers();

        $this->topUpManagers(self::TARGET_ROWS);
        $this->syncManagersToUsers();

        $this->topUpDrivers(self::TARGET_ROWS);
        $this->bootstrapEmptyLowCoverageTables();

        $orderedTables = [
            'ai_price_predictions',
            'vehicles',
            'rides',
            'bookings',
            'payments',
            'reviews',
            'notifications',
            'vehicles_v2',
            'trips',
            'payments_v2',
            'driver_earnings',
            'tickets',
            'activity_logs',
            'driver_locations',
            'driver_wallets',
            'driver_payouts',
            'platform_commissions',
            'fraud_flags',
            'ledger_accounts',
            'ledger_transactions',
            'ledger_entries',
            'ride_requests',
            'passenger_locations',
            'ride_events',
            'ride_cancellations',
            'traffic_events',
            'demand_logs',
            'ai_prediction_logs',
            'ai_model_metrics',
            'domain_events',
            'event_outbox',
            'tax_rules',
            'compliance_reports',
            'demand_zones',
            'driver_behavior_logs',
            'driver_ratings',
            'driver_status',
            'dw_dim_date',
            'dw_dim_driver',
            'dw_dim_passenger',
            'dw_dim_payment_provider',
            'dw_dim_region',
            'dw_fact_commissions',
            'dw_fact_driver_earnings',
            'dw_fact_rides',
            'dw_fact_transactions',
            'fare_audit',
            'ride_feedback',
            'route_checkpoints',
            'user_notifications',
        ];

        foreach ($orderedTables as $table) {
            $this->topUpByCloning($table, self::TARGET_ROWS);
        }
    }

    private function topUpMobileUsers(int $target): void
    {
        if (! Schema::hasTable('mobile_users')) {
            return;
        }

        $count = (int) DB::table('mobile_users')->count();
        $driverCount = (int) DB::table('mobile_users')->where('role', 'DRIVER')->count();

        $rows = [];

        for ($i = $count + 1; $i <= $target; $i++) {
            $first = $this->firstNames[array_rand($this->firstNames)];
            $last = $this->lastNames[array_rand($this->lastNames)];
            $role = $driverCount < self::TARGET_ROWS ? 'DRIVER' : 'PASSENGER';

            $rows[] = [
                'first_name' => $first,
                'last_name' => $last,
                'phone' => $this->rwandaPhone($i),
                'email' => strtolower($first.'.'.$last.".{$i}@riderw.rw"),
                'password' => Hash::make('password123'),
                'role' => $role,
                'profile_photo' => null,
                'is_verified' => true,
                'created_at' => now()->subDays(random_int(1, 365)),
                'updated_at' => now()->subDays(random_int(0, 30)),
            ];

            if ($role === 'DRIVER') {
                $driverCount++;
            }
        }

        if (! empty($rows)) {
            DB::table('mobile_users')->insert($rows);
        }
    }

    private function topUpManagers(int $target): void
    {
        if (! Schema::hasTable('managers')) {
            return;
        }

        $roles = ['SUPER_ADMIN', 'ADMIN', 'OFFICER', 'ACCOUNTANT'];
        $count = (int) DB::table('managers')->count();

        $rows = [];

        for ($i = $count + 1; $i <= $target; $i++) {
            $first = $this->firstNames[array_rand($this->firstNames)];
            $last = $this->lastNames[array_rand($this->lastNames)];

            $rows[] = [
                'name' => $first.' '.$last,
                'email' => strtolower("manager.{$i}.{$last}@rideconnect.rw"),
                'password' => Hash::make('password123'),
                'role' => $roles[$i % count($roles)],
                'created_at' => now()->subDays(random_int(1, 400)),
                'updated_at' => now()->subDays(random_int(0, 45)),
            ];
        }

        if (! empty($rows)) {
            DB::table('managers')->insert($rows);
        }
    }

    private function syncMobileUsersToUsers(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('mobile_users')) {
            return;
        }

        $mobileUsers = DB::table('mobile_users')->get();
        foreach ($mobileUsers as $mobileUser) {
            try {
                DB::table('users')->updateOrInsert(
                    ['email' => $mobileUser->email],
                    [
                        'name' => trim(($mobileUser->first_name ?? '').' '.($mobileUser->last_name ?? '')),
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
            } catch (\Exception $e) {
                // Ignore duplicate constraint violations in seeding
            }
        }
    }

    private function syncManagersToUsers(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('managers')) {
            return;
        }

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

    private function topUpDrivers(int $target): void
    {
        if (! Schema::hasTable('drivers') || ! Schema::hasTable('users')) {
            return;
        }

        $count = (int) DB::table('drivers')->count();
        if ($count >= $target) {
            return;
        }

        $usedUserIds = DB::table('drivers')->pluck('user_id')->all();
        $driverUsers = DB::table('users')
            ->where('role', 'DRIVER')
            ->whereNotIn('id', $usedUserIds)
            ->orderBy('id')
            ->get(['id']);

        $idx = 1;
        foreach ($driverUsers as $driverUser) {
            if ($count >= $target) {
                break;
            }

            DB::table('drivers')->insert([
                'user_id' => $driverUser->id,
                'license_number' => sprintf('DL-RW-2026-%04d', $count + $idx),
                'license_plate' => sprintf('RAB-%03d-%s', (($count + $idx) % 900) + 100, chr(65 + (($count + $idx) % 26))),
                'status' => 'approved',
                'availability_status' => ['online', 'offline', 'busy'][array_rand(['online', 'offline', 'busy'])],
                'current_latitude' => $this->kigaliLat(),
                'current_longitude' => $this->kigaliLng(),
                'last_online_at' => now()->subMinutes(random_int(1, 240)),
                'total_rides' => random_int(0, 240),
                'rating' => round(random_int(35, 50) / 10, 2),
                'rating_count' => random_int(0, 300),
                'balance' => round(random_int(0, 200000) / 100, 2),
                'approved_at' => now()->subDays(random_int(1, 365)),
                'created_at' => now()->subDays(random_int(1, 365)),
                'updated_at' => now()->subDays(random_int(0, 30)),
            ]);

            $count++;
            $idx++;
        }
    }

    private function topUpByCloning(string $table, int $target): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $count = (int) DB::table($table)->count();
        if ($count >= $target) {
            return;
        }

        $samples = DB::table($table)->limit(200)->get()->map(fn ($row) => (array) $row)->all();
        if (empty($samples)) {
            return;
        }

        $columns = $this->getTableColumns($table);
        $columnMap = array_flip($columns);

        $staleAttempts = 0;

        while ($count < $target && $staleAttempts < 6) {
            $needed = $target - $count;
            $batchSize = min(25, $needed);
            $batch = [];

            for ($i = 0; $i < $batchSize; $i++) {
                $seq = $count + $i + 1;
                $row = $samples[array_rand($samples)];
                unset($row['id']);

                $this->mutateRow($table, $row, $seq);

                // Laravel notifications use UUID primary keys; ensure clone inserts always provide one.
                if ($table === 'notifications' && isset($columnMap['id']) && empty($row['id'])) {
                    $row['id'] = (string) Str::uuid();
                }

                $batch[] = array_intersect_key($row, $columnMap);
            }

            if (empty($batch)) {
                break;
            }

            // Ignore duplicate-key collisions instead of thrashing with exception retries.
            $inserted = DB::table($table)->insertOrIgnore($batch);

            if ($inserted <= 0) {
                $staleAttempts++;

                continue;
            }

            $count += $inserted;
            $staleAttempts = 0;
        }

        if ($this->command) {
            $this->command->getOutput()->writeln(sprintf(
                'RwandaFiftyTopUpSeeder: %s rows=%d/%d',
                $table,
                $count,
                $target
            ));
        }
    }

    private function bootstrapEmptyLowCoverageTables(): void
    {
        $this->bootstrapAiPricePrediction();
        $this->bootstrapComplianceReports();
        $this->bootstrapDomainEvents();
        $this->bootstrapEventOutbox();
        $this->bootstrapDriverBehaviorLogs();
        $this->bootstrapDriverRatings();
        $this->bootstrapDriverStatus();
        $this->bootstrapDwDimensions();
        $this->bootstrapDwFacts();
        $this->bootstrapFareAudit();
        $this->bootstrapLedger();
        $this->bootstrapNotifications();
        $this->bootstrapRideFeedback();
        $this->bootstrapRouteCheckpoints();
        $this->bootstrapTaxRules();
    }

    private function mutateRow(string $table, array &$row, int $seq): void
    {
        $tsColumns = [
            'created_at', 'updated_at', 'requested_at', 'accepted_at', 'started_at', 'completed_at',
            'paid_at', 'refunded_at', 'departure_time', 'arrival_time_estimated', 'approved_at',
            'last_online_at', 'event_time', 'cancelled_at', 'processed_at', 'evaluated_at',
        ];

        foreach ($tsColumns as $column) {
            if (array_key_exists($column, $row)) {
                $row[$column] = now()->subMinutes(random_int(1, 60 * 24 * 120));
            }
        }

        if (array_key_exists('email', $row)) {
            $row['email'] = "rw.seed.{$table}.{$seq}@rideconnect.rw";
        }

        if (array_key_exists('phone', $row)) {
            $row['phone'] = $this->rwandaPhone($seq + random_int(100, 900));
        }

        foreach (['transaction_reference', 'provider_transaction_id', 'webhook_event_id', 'token'] as $column) {
            if (array_key_exists($column, $row)) {
                $row[$column] = strtoupper(Str::random(18));
            }
        }

        if (array_key_exists('transaction_id', $row)) {
            if ($table === 'ledger_entries') {
                $row['transaction_id'] = $this->randomIdFromTable('ledger_transactions') ?? $row['transaction_id'];
            } elseif (! is_numeric($row['transaction_id'])) {
                $row['transaction_id'] = strtoupper(Str::random(18));
            }
        }

        foreach (['uuid', 'event_id'] as $column) {
            if (array_key_exists($column, $row)) {
                $row[$column] = (string) Str::uuid();
            }
        }

        if ($table === 'notifications') {
            $row['id'] = (string) Str::uuid();
        }

        if (array_key_exists('license_number', $row)) {
            $row['license_number'] = sprintf('DL-RW-2026-T%04d', $seq);
        }

        if (array_key_exists('license_plate', $row)) {
            $row['license_plate'] = sprintf('RAA-%03d-%s', ($seq % 900) + 100, chr(65 + ($seq % 26)));
        }

        if (array_key_exists('region_code', $row)) {
            $row['region_code'] = 'RW-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
        }

        if (array_key_exists('provider_code', $row)) {
            $row['provider_code'] = 'rw_provider_'.$seq;
        }

        if (array_key_exists('zone_key', $row)) {
            $district = self::RWANDA_DISTRICTS[array_rand(self::RWANDA_DISTRICTS)];
            $spot = self::RWANDA_HOTSPOTS[array_rand(self::RWANDA_HOTSPOTS)];
            $row['zone_key'] = strtoupper($district).':'.Str::slug($spot, '_');
        }

        if (array_key_exists('pickup_location', $row)) {
            $row['pickup_location'] = self::RWANDA_HOTSPOTS[array_rand(self::RWANDA_HOTSPOTS)].', '.self::RWANDA_DISTRICTS[array_rand(self::RWANDA_DISTRICTS)];
        }

        if (array_key_exists('dropoff_location', $row)) {
            $row['dropoff_location'] = self::RWANDA_HOTSPOTS[array_rand(self::RWANDA_HOTSPOTS)].', '.self::RWANDA_DISTRICTS[array_rand(self::RWANDA_DISTRICTS)];
        }

        if (array_key_exists('origin_address', $row)) {
            $row['origin_address'] = self::RWANDA_HOTSPOTS[array_rand(self::RWANDA_HOTSPOTS)].', Kigali, Rwanda';
        }

        if (array_key_exists('destination_address', $row)) {
            $row['destination_address'] = self::RWANDA_HOTSPOTS[array_rand(self::RWANDA_HOTSPOTS)].', Rwanda';
        }

        foreach (['pickup_lat', 'dropoff_lat', 'origin_lat', 'destination_lat', 'latitude', 'current_latitude'] as $column) {
            if (array_key_exists($column, $row)) {
                $row[$column] = $this->kigaliLat();
            }
        }

        foreach (['pickup_lng', 'dropoff_lng', 'origin_lng', 'destination_lng', 'longitude', 'current_longitude'] as $column) {
            if (array_key_exists($column, $row)) {
                $row[$column] = $this->kigaliLng();
            }
        }

        if ($table === 'driver_wallets' && array_key_exists('driver_id', $row) && Schema::hasTable('drivers')) {
            $row['driver_id'] = $this->nextUniqueReferenceId('driver_wallets', 'driver_id', 'drivers');
        }

        if ($table === 'driver_locations' && array_key_exists('driver_id', $row) && Schema::hasTable('drivers')) {
            $row['driver_id'] = $this->nextUniqueReferenceId('driver_locations', 'driver_id', 'drivers');
        }

        if ($table === 'driver_status' && array_key_exists('driver_id', $row) && Schema::hasTable('drivers')) {
            $row['driver_id'] = $this->nextUniqueReferenceId('driver_status', 'driver_id', 'drivers');
        }

        if ($table === 'passenger_locations' && array_key_exists('passenger_id', $row) && Schema::hasTable('mobile_users')) {
            $row['passenger_id'] = $this->nextUniquePassengerId();
        }

        if ($table === 'driver_payouts') {
            if (array_key_exists('driver_id', $row) && Schema::hasTable('drivers')) {
                $driverIds = DB::table('drivers')->pluck('id')->all();
                if (! empty($driverIds)) {
                    $row['driver_id'] = $driverIds[array_rand($driverIds)];
                }
            }
            if (array_key_exists('payout_date', $row)) {
                $row['payout_date'] = now()->subDays($seq)->toDateString();
            }
        }

        if ($table === 'platform_commissions') {
            if (array_key_exists('driver_id', $row) && Schema::hasTable('drivers')) {
                $driverIds = DB::table('drivers')->pluck('id')->all();
                if (! empty($driverIds)) {
                    $row['driver_id'] = $driverIds[array_rand($driverIds)];
                }
            }
            if (array_key_exists('ride_id', $row) && Schema::hasTable('rides')) {
                $rideIds = DB::table('rides')->pluck('id')->all();
                if (! empty($rideIds)) {
                    $row['ride_id'] = $rideIds[array_rand($rideIds)];
                }
            }
            if (array_key_exists('date', $row)) {
                $row['date'] = now()->subDays($seq)->toDateString();
            }
        }

        if ($table === 'driver_earnings' && array_key_exists('driver_id', $row) && Schema::hasTable('drivers')) {
            $driverIds = DB::table('drivers')->pluck('id')->all();
            if (! empty($driverIds)) {
                $row['driver_id'] = $driverIds[array_rand($driverIds)];
            }

            if (array_key_exists('trip_id', $row) && Schema::hasTable('trips')) {
                $tripIds = DB::table('trips')->pluck('id')->all();
                if (! empty($tripIds)) {
                    $row['trip_id'] = $tripIds[array_rand($tripIds)];
                }
            }
        }

        if ($table === 'trips' && array_key_exists('driver_id', $row) && Schema::hasTable('drivers')) {
            $driverIds = DB::table('drivers')->pluck('id')->all();
            if (! empty($driverIds)) {
                $row['driver_id'] = $driverIds[array_rand($driverIds)];
            }
            if (array_key_exists('passenger_id', $row) && Schema::hasTable('mobile_users')) {
                $passengerIds = DB::table('mobile_users')->where('role', 'PASSENGER')->pluck('id')->all();
                if (! empty($passengerIds)) {
                    $row['passenger_id'] = $passengerIds[array_rand($passengerIds)];
                }
            }
            if (array_key_exists('status', $row)) {
                $row['status'] = ['PENDING', 'ACCEPTED', 'STARTED', 'COMPLETED', 'CANCELLED'][array_rand(['PENDING', 'ACCEPTED', 'STARTED', 'COMPLETED', 'CANCELLED'])];
            }
        }

        if (array_key_exists('date_key', $row)) {
            $row['date_key'] = now()->subDays($seq)->toDateString();
        }

        if (array_key_exists('name', $row) && $table === 'ledger_accounts') {
            $row['name'] = 'Account '.$seq;
        }

        if (array_key_exists('report_name', $row) && $table === 'compliance_reports') {
            $row['report_name'] = 'Rwanda Compliance Snapshot #'.$seq;
        }
    }

    private function nextUniqueReferenceId(string $targetTable, string $column, string $sourceTable): ?int
    {
        $sourcePool = $this->getIdPool($sourceTable);
        if (empty($sourcePool)) {
            return null;
        }

        $cacheKey = $targetTable.':'.$column;
        if (! array_key_exists($cacheKey, $this->usedReferenceIds)) {
            $this->usedReferenceIds[$cacheKey] = DB::table($targetTable)->pluck($column)->filter()->map(fn ($id) => (int) $id)->all();
        }

        $usedMap = array_flip($this->usedReferenceIds[$cacheKey]);
        foreach ($sourcePool as $candidate) {
            if (! isset($usedMap[$candidate])) {
                $this->usedReferenceIds[$cacheKey][] = $candidate;

                return $candidate;
            }
        }

        $fallback = $sourcePool[array_rand($sourcePool)];
        $this->usedReferenceIds[$cacheKey][] = $fallback;

        return $fallback;
    }

    private function nextUniquePassengerId(): ?int
    {
        $sourcePool = $this->getIdPool('mobile_users', 'role', 'PASSENGER');
        if (empty($sourcePool)) {
            return null;
        }

        $cacheKey = 'passenger_locations:passenger_id';
        if (! array_key_exists($cacheKey, $this->usedReferenceIds)) {
            $this->usedReferenceIds[$cacheKey] = DB::table('passenger_locations')
                ->pluck('passenger_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $usedMap = array_flip($this->usedReferenceIds[$cacheKey]);
        foreach ($sourcePool as $candidate) {
            if (! isset($usedMap[$candidate])) {
                $this->usedReferenceIds[$cacheKey][] = $candidate;

                return $candidate;
            }
        }

        $fallback = $sourcePool[array_rand($sourcePool)];
        $this->usedReferenceIds[$cacheKey][] = $fallback;

        return $fallback;
    }

    /**
     * @return array<int, string>
     */
    private function getTableColumns(string $table): array
    {
        if (! array_key_exists($table, $this->columnCache)) {
            $this->columnCache[$table] = Schema::getColumnListing($table);
        }

        return $this->columnCache[$table];
    }

    /**
     * @return array<int, int>
     */
    private function getIdPool(string $table, ?string $whereColumn = null, mixed $whereValue = null): array
    {
        $key = $table.'|'.($whereColumn ?? '').'|'.(is_scalar($whereValue) ? (string) $whereValue : '');

        if (! array_key_exists($key, $this->idPools)) {
            $query = DB::table($table)->select('id');

            if ($whereColumn !== null) {
                $query->where($whereColumn, $whereValue);
            }

            $this->idPools[$key] = $query->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        }

        return $this->idPools[$key];
    }

    private function rwandaPhone(int $seq): string
    {
        // Rwanda mobile prefixes are commonly +25078x and +25079x.
        $prefix = ['+25078', '+25079'][array_rand(['+25078', '+25079'])];

        return $prefix.str_pad((string) (($seq % 10000000) + 1000000), 7, '0', STR_PAD_LEFT);
    }

    private function kigaliLat(): float
    {
        return round(-1.9441 + random_int(-300, 300) / 10000, 7);
    }

    private function kigaliLng(): float
    {
        return round(30.0619 + random_int(-300, 300) / 10000, 7);
    }

    private function randomIdFromTable(string $table): ?int
    {
        $pool = $this->getIdPool($table);
        if (empty($pool)) {
            return null;
        }

        return $pool[array_rand($pool)];
    }

    private function bootstrapAiPricePrediction(): void
    {
        if (! Schema::hasTable('ai_price_predictions') || DB::table('ai_price_predictions')->exists()) {
            return;
        }

        DB::table('ai_price_predictions')->insert([
            'distance_km' => 6.4,
            'demand_level' => 3,
            'traffic_level' => 2,
            'ride_type' => 'standard',
            'predicted_price' => 5400,
            'created_at' => now(),
        ]);
    }

    private function bootstrapComplianceReports(): void
    {
        if (! Schema::hasTable('compliance_reports') || DB::table('compliance_reports')->exists()) {
            return;
        }

        DB::table('compliance_reports')->insert([
            'report_type' => 'weekly_operations',
            'period_start' => now()->subDays(7)->toDateString(),
            'period_end' => now()->toDateString(),
            'generated_by' => DB::table('users')->value('id'),
            'file_path' => 'reports/compliance/seed.csv',
            'status' => 'ready',
            'summary_data' => json_encode(['country' => 'RW', 'notes' => 'seeded snapshot']),
            'metadata' => json_encode(['source' => 'rwanda_topup']),
            'generated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function bootstrapDomainEvents(): void
    {
        if (! Schema::hasTable('domain_events') || DB::table('domain_events')->exists()) {
            return;
        }

        DB::table('domain_events')->insert([
            'event_id' => (string) Str::uuid(),
            'event_type' => 'ride.requested',
            'aggregate_id' => 'seed-ride-1',
            'aggregate_type' => 'Ride',
            'payload' => json_encode(['country' => 'RW', 'seed' => true]),
            'occurred_at' => now(),
            'processed' => false,
            'retry_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
            'payload_hash' => hash('sha256', 'seed-ride-1'),
        ]);
    }

    private function bootstrapEventOutbox(): void
    {
        if (! Schema::hasTable('event_outbox') || DB::table('event_outbox')->exists()) {
            return;
        }

        DB::table('event_outbox')->insert([
            'event_id' => (string) Str::uuid(),
            'event_type' => 'ride.requested',
            'aggregate_id' => 'seed-ride-1',
            'aggregate_type' => 'Ride',
            'payload' => json_encode(['country' => 'RW', 'seed' => true]),
            'occurred_at' => now(),
            'status' => 'pending',
            'attempts' => 0,
            'topic' => 'rides.events',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function bootstrapDriverBehaviorLogs(): void
    {
        if (! Schema::hasTable('driver_behavior_logs') || DB::table('driver_behavior_logs')->exists()) {
            return;
        }

        $driverId = DB::table('drivers')->value('id');
        if (! $driverId) {
            return;
        }

        DB::table('driver_behavior_logs')->insert([
            'driver_id' => $driverId,
            'trip_id' => DB::table('trips')->value('id'),
            'behavior_class' => 'safe',
            'confidence' => 0.92,
            'avg_speed_kmh' => 34.5,
            'route_deviation_pct' => 0.08,
            'cancellation_rate' => 0.03,
            'avg_rating' => 4.6,
            'raw_features' => json_encode(['country' => 'RW']),
            'analyzed_at' => now(),
        ]);
    }

    private function bootstrapDriverRatings(): void
    {
        if (! Schema::hasTable('driver_ratings') || DB::table('driver_ratings')->exists()) {
            return;
        }

        $driverId = DB::table('drivers')->value('id');
        if (! $driverId) {
            return;
        }

        DB::table('driver_ratings')->insert([
            'driver_id' => $driverId,
            'trip_id' => DB::table('trips')->value('id'),
            'rating' => 4.7,
            'punctuality' => 4.6,
            'safety' => 4.8,
            'communication' => 4.5,
            'comment' => 'Smooth Kigali trip experience',
            'rated_by' => DB::table('mobile_users')->where('role', 'PASSENGER')->value('id'),
            'created_at' => now(),
        ]);
    }

    private function bootstrapDriverStatus(): void
    {
        if (! Schema::hasTable('driver_status') || DB::table('driver_status')->exists()) {
            return;
        }

        $driverId = DB::table('drivers')->value('id');
        if (! $driverId) {
            return;
        }

        DB::table('driver_status')->insert([
            'driver_id' => $driverId,
            'status' => 'online',
            'last_seen' => now(),
            'idle_since' => now()->subMinutes(8),
            'updated_at' => now(),
        ]);
    }

    private function bootstrapDwDimensions(): void
    {
        if (Schema::hasTable('dw_dim_date') && ! DB::table('dw_dim_date')->exists()) {
            $day = now();
            DB::table('dw_dim_date')->insert([
                'date_key' => $day->toDateString(),
                'year' => (int) $day->format('Y'),
                'month' => (int) $day->format('n'),
                'day' => (int) $day->format('j'),
                'day_of_week' => (int) $day->format('N'),
                'quarter' => (int) ceil(((int) $day->format('n')) / 3),
                'month_name' => $day->format('F'),
                'day_name' => $day->format('l'),
                'is_weekend' => in_array((int) $day->format('N'), [6, 7], true),
                'is_holiday' => false,
            ]);
        }

        if (Schema::hasTable('dw_dim_driver') && ! DB::table('dw_dim_driver')->exists()) {
            $driver = DB::table('drivers')->first();
            if ($driver) {
                DB::table('dw_dim_driver')->insert([
                    'driver_id' => $driver->id,
                    'driver_name' => 'Driver '.$driver->id,
                    'phone' => DB::table('users')->where('id', $driver->user_id)->value('phone'),
                    'vehicle_class' => 'Standard',
                    'region' => 'Kigali',
                    'joined_date' => now()->subMonths(6)->toDateString(),
                    'is_active' => true,
                    'effective_from' => now()->toDateString(),
                    'is_current' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (Schema::hasTable('dw_dim_passenger') && ! DB::table('dw_dim_passenger')->exists()) {
            $passenger = DB::table('mobile_users')->where('role', 'PASSENGER')->first();
            if ($passenger) {
                DB::table('dw_dim_passenger')->insert([
                    'passenger_id' => $passenger->id,
                    'passenger_name' => trim(($passenger->first_name ?? '').' '.($passenger->last_name ?? '')),
                    'phone' => $passenger->phone,
                    'registered_date' => now()->subMonths(4)->toDateString(),
                    'is_current' => true,
                    'effective_from' => now()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (Schema::hasTable('dw_dim_region') && ! DB::table('dw_dim_region')->exists()) {
            DB::table('dw_dim_region')->insert([
                'region_code' => 'RW-KGL',
                'region_name' => 'Kigali',
                'province' => 'Kigali City',
                'country_code' => 'RW',
            ]);
        }
    }

    private function bootstrapDwFacts(): void
    {
        $dateKey = now()->toDateString();
        $driverDimId = Schema::hasTable('dw_dim_driver') ? DB::table('dw_dim_driver')->value('id') : null;
        $passengerDimId = Schema::hasTable('dw_dim_passenger') ? DB::table('dw_dim_passenger')->value('id') : null;
        $regionDimId = Schema::hasTable('dw_dim_region') ? DB::table('dw_dim_region')->value('id') : null;
        $providerDimId = Schema::hasTable('dw_dim_payment_provider') ? DB::table('dw_dim_payment_provider')->value('id') : null;

        if (Schema::hasTable('dw_fact_driver_earnings') && ! DB::table('dw_fact_driver_earnings')->exists() && $driverDimId) {
            DB::table('dw_fact_driver_earnings')->insert([
                'date_key' => $dateKey,
                'driver_dim_id' => $driverDimId,
                'region_dim_id' => $regionDimId,
                'total_rides' => 5,
                'gross_earnings' => 18000,
                'commission_deducted' => 2700,
                'tax_withheld' => 540,
                'net_payout' => 14760,
                'avg_ride_fare' => 3600,
                'etl_batch_id' => (string) Str::uuid(),
            ]);
        }

        if (Schema::hasTable('dw_fact_commissions') && ! DB::table('dw_fact_commissions')->exists()) {
            DB::table('dw_fact_commissions')->insert([
                'date_key' => $dateKey,
                'driver_dim_id' => $driverDimId,
                'payment_provider_dim_id' => $providerDimId,
                'total_commission' => 2700,
                'tax_on_commission' => 540,
                'net_commission' => 2160,
                'transaction_count' => 5,
                'etl_batch_id' => (string) Str::uuid(),
            ]);
        }

        if (Schema::hasTable('dw_fact_rides') && ! DB::table('dw_fact_rides')->exists()) {
            DB::table('dw_fact_rides')->insert([
                'date_key' => $dateKey,
                'driver_dim_id' => $driverDimId,
                'passenger_dim_id' => $passengerDimId,
                'region_dim_id' => $regionDimId,
                'source_ride_id' => DB::table('rides')->value('id'),
                'ride_status' => 'completed',
                'fare_amount' => 5400,
                'distance_km' => 6.4,
                'duration_minutes' => 22,
                'surge_multiplier' => 1.10,
                'pickup_at' => now()->subMinutes(30),
                'dropoff_at' => now()->subMinutes(8),
                'etl_batch_id' => (string) Str::uuid(),
            ]);
        }

        if (Schema::hasTable('dw_fact_transactions') && ! DB::table('dw_fact_transactions')->exists()) {
            DB::table('dw_fact_transactions')->insert([
                'date_key' => $dateKey,
                'driver_dim_id' => $driverDimId,
                'passenger_dim_id' => $passengerDimId,
                'payment_provider_dim_id' => $providerDimId,
                'region_dim_id' => $regionDimId,
                'ledger_transaction_id' => Schema::hasTable('ledger_transactions') ? DB::table('ledger_transactions')->value('id') : null,
                'transaction_type' => 'ride_payment',
                'gross_amount' => 5400,
                'commission_amount' => 810,
                'driver_payout' => 4320,
                'tax_amount' => 270,
                'net_platform_revenue' => 540,
                'currency' => 'RWF',
                'etl_batch_id' => (string) Str::uuid(),
            ]);
        }
    }

    private function bootstrapFareAudit(): void
    {
        if (! Schema::hasTable('fare_audit') || DB::table('fare_audit')->exists()) {
            return;
        }

        DB::table('fare_audit')->insert([
            'trip_id' => DB::table('trips')->value('id'),
            'ride_id' => DB::table('rides')->value('id'),
            'original_fare' => 5200,
            'predicted_fare' => 5400,
            'anomaly_flag' => false,
            'created_at' => now(),
        ]);
    }

    private function bootstrapLedger(): void
    {
        if (Schema::hasTable('ledger_transactions') && ! DB::table('ledger_transactions')->exists()) {
            DB::table('ledger_transactions')->insert([
                'uuid' => (string) Str::uuid(),
                'description' => 'Seeded Rwanda ledger transaction',
                'created_by' => DB::table('users')->value('id'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('ledger_entries') && ! DB::table('ledger_entries')->exists()) {
            $accountId = DB::table('ledger_accounts')->value('id');
            $transactionId = DB::table('ledger_transactions')->value('id');
            if ($accountId && $transactionId) {
                DB::table('ledger_entries')->insert([
                    'account_id' => $accountId,
                    'transaction_id' => $transactionId,
                    'debit' => 5400,
                    'credit' => 0,
                    'reference_type' => 'ride',
                    'reference_id' => DB::table('rides')->value('id'),
                    'description' => 'Seeded entry',
                    'created_at' => now(),
                ]);
            }
        }
    }

    private function bootstrapNotifications(): void
    {
        if (! Schema::hasTable('notifications') || DB::table('notifications')->exists()) {
            return;
        }

        $userId = DB::table('users')->value('id');
        if (! $userId) {
            return;
        }

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\RideStatusUpdated',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $userId,
            'data' => json_encode(['title' => 'Ride update', 'message' => 'Your ride was accepted.']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function bootstrapRideFeedback(): void
    {
        if (! Schema::hasTable('ride_feedback') || DB::table('ride_feedback')->exists()) {
            return;
        }

        DB::table('ride_feedback')->insert([
            'trip_id' => DB::table('trips')->value('id'),
            'user_id' => DB::table('mobile_users')->where('role', 'PASSENGER')->value('id'),
            'rating' => 5,
            'category' => 'service',
            'comment' => 'Great ride in Kigali.',
            'created_at' => now(),
        ]);
    }

    private function bootstrapRouteCheckpoints(): void
    {
        if (! Schema::hasTable('route_checkpoints') || DB::table('route_checkpoints')->exists()) {
            return;
        }

        DB::table('route_checkpoints')->insert([
            'trip_id' => DB::table('trips')->value('id'),
            'sequence' => 1,
            'latitude' => $this->kigaliLat(),
            'longitude' => $this->kigaliLng(),
            'name' => 'Kigali CBD checkpoint',
            'is_mandatory' => true,
            'created_at' => now(),
        ]);
    }

    private function bootstrapTaxRules(): void
    {
        if (! Schema::hasTable('tax_rules') || DB::table('tax_rules')->exists()) {
            return;
        }

        DB::table('tax_rules')->insert([
            'tax_name' => 'Rwanda City Service Tax',
            'percentage' => 0.18,
            'applies_to' => 'ride',
            'jurisdiction' => 'RW',
            'active' => true,
            'effective_from' => now()->subYear()->toDateString(),
            'description' => 'Rwanda VAT on ride fares',
            'created_by' => DB::table('users')->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
