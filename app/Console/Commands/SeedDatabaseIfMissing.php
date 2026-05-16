<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedDatabaseIfMissing extends Command
{
    protected $signature = 'app:seed-database {--marker=production-default : Marker used to avoid duplicate production seeding} {--force : Seed even if the marker already exists}';

    protected $description = 'Seed baseline data once per database when the seed marker is missing';

    public function handle(): int
    {
        $marker = (string) $this->option('marker');
        $force = (bool) $this->option('force');
        $adoptExisting = filter_var((string) env('DB_SEED_ADOPT_EXISTING', 'true'), FILTER_VALIDATE_BOOL);

        if (! $force && $this->hasMarker($marker)) {
            $this->info(sprintf('Seed marker "%s" already exists; skipping database seeding.', $marker));

            return self::SUCCESS;
        }

        if (! $force && $adoptExisting && $this->databaseLooksSeeded()) {
            $this->recordMarker($marker, [
                'mode' => 'adopt-existing',
                'users' => $this->safeCount('users'),
                'managers' => $this->safeCount('managers'),
                'mobile_users' => $this->safeCount('mobile_users'),
                'rides' => $this->safeCount('rides'),
            ]);

            $this->info(sprintf('Baseline data already exists; marker "%s" recorded without re-seeding.', $marker));

            return self::SUCCESS;
        }

        $this->info('Seeding database with DatabaseSeeder...');

        try {
            $exitCode = Artisan::call('db:seed', [
                '--force' => true,
                '--no-interaction' => true,
            ]);

            if ($exitCode !== self::SUCCESS) {
                $this->error(trim((string) Artisan::output()));

                return self::FAILURE;
            }

            $this->recordMarker($marker, [
                'mode' => 'seeded',
                'seeded_by' => 'app:seed-database',
            ]);

            $this->info(sprintf('Database seeded successfully and marker "%s" recorded.', $marker));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            report($e);
            $this->error('Database seeding failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function hasMarker(string $marker): bool
    {
        $this->ensureMarkerTableExists();

        return DB::table('seed_runs')
            ->where('name', $marker)
            ->exists();
    }

    private function ensureMarkerTableExists(): void
    {
        if (Schema::hasTable('seed_runs')) {
            return;
        }

        Schema::create('seed_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->json('meta')->nullable();
            $table->timestampTz('seeded_at')->useCurrent();
        });
    }

    private function recordMarker(string $marker, array $meta = []): void
    {
        $this->ensureMarkerTableExists();

        DB::table('seed_runs')->updateOrInsert(
            ['name' => $marker],
            [
                'meta' => json_encode(array_merge($meta, [
                    'seeded_at' => now()->toIso8601String(),
                ]), JSON_THROW_ON_ERROR),
                'seeded_at' => now(),
            ]
        );
    }

    private function databaseLooksSeeded(): bool
    {
        foreach (['users', 'managers', 'mobile_users'] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return $this->safeCount('users') > 0
            && $this->safeCount('managers') > 0
            && $this->safeCount('mobile_users') > 0;
    }

    private function safeCount(string $table): int
    {
        try {
            return (int) DB::table($table)->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
