<?php

namespace App\Console\Commands;

use App\Services\DatabaseTableProtectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class MigrateSeedAndProtect extends Command
{
    protected $signature = 'db:migrate-seed-protect
                            {--seed-marker=rideconnect-production : Marker for one-time production seeding}
                            {--force-seed : Run full DatabaseSeeder (idempotent upserts/firstOrCreate)}
                            {--use-marker : Use app:seed-database marker instead of full DatabaseSeeder}
                            {--skip-seed : Skip seeding step}
                            {--policy-only : Lock only policy tables instead of all tables}';

    protected $description = 'Run migrations, seed all baseline data, and lock all tables against destructive drops';

    public function handle(DatabaseTableProtectionService $protection): int
    {
        $this->info('Step 1/3: Running migrations (additive only)...');

        $migrateExit = Artisan::call('migrate', [
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $this->output->write(Artisan::output());

        if ($migrateExit !== self::SUCCESS) {
            $this->error('Migration failed. Seeding and table lock were skipped.');

            return self::FAILURE;
        }

        if (! $this->option('skip-seed')) {
            $this->info('Step 2/3: Seeding database...');

            if ($this->option('use-marker')) {
                $seedExit = Artisan::call('app:seed-database', [
                    '--marker' => (string) $this->option('seed-marker'),
                    '--force' => $this->option('force-seed'),
                ]);
            } else {
                $seedExit = Artisan::call('db:seed', [
                    '--force' => true,
                    '--no-interaction' => true,
                ]);
            }

            $this->output->write(Artisan::output());

            if ($seedExit !== self::SUCCESS) {
                $this->error('Seeding failed. Table lock step was skipped.');

                return self::FAILURE;
            }
        } else {
            $this->warn('Step 2/3: Seeding skipped (--skip-seed).');
        }

        $this->info('Step 3/3: Locking tables against DROP/TRUNCATE...');

        $result = $this->option('policy-only')
            ? $protection->lockPolicyTables('db:migrate-seed-protect policy lock')
            : $protection->lockAllTables('db:migrate-seed-protect full lock');

        $lockedCount = $result['locked'] ?? 0;
        $this->info("Locked {$lockedCount} tables.");

        $tables = $protection->discoverTables();
        $this->table(
            ['Schema', 'Table'],
            array_map(static fn (array $table) => [$table['schema_name'], $table['table_name']], $tables)
        );

        $this->info('Database migrate + seed + protect completed successfully.');

        return self::SUCCESS;
    }
}
