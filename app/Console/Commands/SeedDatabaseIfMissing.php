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

        if (!$force && $this->hasMarker($marker)) {
            $this->info(sprintf('Seed marker "%s" already exists; skipping database seeding.', $marker));

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

            DB::table('seed_runs')->updateOrInsert(
                ['name' => $marker],
                [
                    'meta' => json_encode([
                        'seeded_by' => 'app:seed-database',
                        'seeded_at' => now()->toIso8601String(),
                    ], JSON_THROW_ON_ERROR),
                    'seeded_at' => now(),
                ]
            );

            $this->info(sprintf('Database seeded successfully and marker "%s" recorded.', $marker));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            report($e);
            $this->error('Database seeding failed: ' . $e->getMessage());

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
}