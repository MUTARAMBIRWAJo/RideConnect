<?php

namespace Tests\Feature;

use App\Services\DatabaseTableProtectionService;
use App\Services\MigrationSafetyService;
use Illuminate\Support\Facades\Artisan;
use ReflectionMethod;
use RuntimeException;
use Tests\LightweightTestCase;

class MigrationSafetyGuardTest extends LightweightTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database_protection.enabled' => true,
            'database_protection.enable_during_tests' => true,
            'database_protection.guard_environments' => ['testing'],
        ]);

        app()->detectEnvironment(fn () => 'testing');
    }

    public function test_migrate_fresh_is_blocked_even_with_approval_flag(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('migrate:fresh');

        $_SERVER['argv'] = ['artisan', 'migrate:fresh', '--force', '--approve-destructive'];

        $this->invokeCommandGuard('migrate:fresh');
    }

    public function test_migrate_rollback_requires_approval_in_guarded_environment(): void
    {
        $_SERVER['argv'] = ['artisan', 'migrate:rollback', '--force'];

        try {
            $this->invokeCommandGuard('migrate:rollback');
            $this->fail('Expected rollback to be blocked without approval.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('--approve-destructive', $exception->getMessage());
        }
    }

    public function test_migrate_audit_command_runs_successfully(): void
    {
        config(['database_protection.enable_during_tests' => false]);

        $exitCode = Artisan::call('migrate:audit', [
            '--save-report' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Migration Safety Audit', Artisan::output());
    }

    public function test_has_destructive_approval_reads_flag(): void
    {
        $service = app(MigrationSafetyService::class);

        $_SERVER['argv'] = ['artisan', 'migrate', '--approve-destructive'];

        $this->assertTrue($service->hasDestructiveApproval(null));

        $_SERVER['argv'] = ['artisan', 'migrate'];
    }

    private function invokeCommandGuard(string $command): void
    {
        $protection = app(DatabaseTableProtectionService::class);
        $method = new ReflectionMethod($protection, 'assertCommandAllowed');
        $method->setAccessible(true);
        $method->invoke($protection, $command, null);
    }
}
