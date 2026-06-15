<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait IdempotentJob
{
    /**
     * Get a unique deterministic key for this job based on its properties.
     */
    public function getIdempotencyKey(): string
    {
        $properties = get_object_vars($this);
        
        // Remove standard Laravel Job properties to only serialize parameters
        $ignoredProperties = [
            'job', 'connection', 'queue', 'chainConnection', 'chainQueue', 
            'chainCatchCallbacks', 'delay', 'afterCommit', 'middleware', 'tries', 'timeout', 'backoff'
        ];
        
        foreach ($ignoredProperties as $prop) {
            unset($properties[$prop]);
        }
        
        return get_class($this) . ':' . md5(serialize($properties));
    }

    /**
     * Check and mark this job as processed.
     * Returns true if this is the first execution, false if it's a duplicate.
     */
    protected function startProcessing(): bool
    {
        $key = $this->getIdempotencyKey();

        try {
            return DB::transaction(function () use ($key) {
                $exists = DB::table('job_idempotency')
                    ->where('job_key', $key)
                    ->lockForUpdate()
                    ->exists();

                if ($exists) {
                    return false;
                }

                DB::table('job_idempotency')->insert([
                    'job_key' => $key,
                    'processed_at' => now(),
                ]);

                return true;
            });
        } catch (\Throwable $e) {
            Log::error("[IdempotentJob] Database error acquiring lock for key: {$key}", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
