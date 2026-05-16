<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Carbon;
use Mockery;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase()
    {
        // SQLite in-memory tests cannot rely on this suite's current nested
        // transaction mix, so rebuild the schema per test instead.
        RefreshDatabaseState::$migrated = false;
    }

    public function beginDatabaseTransaction()
    {
        // Isolation is provided by migrate:fresh for each test.
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }
}
