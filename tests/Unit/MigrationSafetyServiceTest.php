<?php

namespace Tests\Unit;

use App\Services\MigrationSafetyService;
use Tests\LightweightTestCase;

class MigrationSafetyServiceTest extends LightweightTestCase
{
    public function test_audit_detects_destructive_down_methods(): void
    {
        $service = app(MigrationSafetyService::class);
        $audit = $service->auditAllMigrations();

        $this->assertGreaterThan(0, $audit['summary']['total_files']);
        $this->assertGreaterThan(0, $audit['summary']['destructive_down']);

        $tripsMigration = collect($audit['files'])->first(
            fn (array $file) => $file['file'] === '2025_02_25_000004_create_trips_table.php'
        );

        $this->assertNotNull($tripsMigration);
        $this->assertTrue($tripsMigration['down']['is_destructive']);
        $this->assertContains('trips', $tripsMigration['down']['tables']);
    }

    public function test_report_is_written_to_storage(): void
    {
        $service = app(MigrationSafetyService::class);
        $analysis = $service->analyzeMigrationFile(
            database_path('migrations/2025_02_25_000004_create_trips_table.php')
        );

        $path = $service->generateReport([$analysis], [
            'action' => 'unit_test',
            'approved' => false,
            'reason' => 'Unit test report generation',
        ]);

        $this->assertFileExists($path);
        $this->assertFileExists(str_replace('.json', '.md', $path));

        $payload = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('unit_test', $payload['action']);
        $this->assertContains('trips', $payload['affected_tables']);
    }
}
