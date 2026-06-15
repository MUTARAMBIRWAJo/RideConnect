<?php

namespace App\Services\Sync;

class PostgresSyncContext
{
    protected static bool $isSyncing = false;

    /**
     * Check if currently running inside a safe PostgreSQL sync context.
     */
    public static function isSyncing(): bool
    {
        return self::$isSyncing;
    }

    /**
     * Explicitly set the sync state indicator.
     */
    public static function setSyncing(bool $value): void
    {
        self::$isSyncing = $value;
    }

    /**
     * Run a callback inside a secure sync context.
     * Guaranteed to restore the original context state when done.
     */
    public static function run(callable $callback)
    {
        $old = self::$isSyncing;
        self::$isSyncing = true;
        try {
            return $callback();
        } finally {
            self::$isSyncing = $old;
        }
    }
}
