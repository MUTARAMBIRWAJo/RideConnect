<?php

namespace App\Services\Health\Checks;

use Illuminate\Support\Facades\File;

class StorageHealthCheck
{
    /**
     * @return array<string, mixed>
     */
    public function check(bool $extended = false): array
    {
        $timeoutMs = (int) config('health.timeouts.storage_ms', 1000);

        return \App\Services\HealthCheckService::timed(function () use ($extended) {
            $paths = config('health.storage_paths', [
                'framework/cache',
                'framework/sessions',
                'framework/views',
                'logs',
            ]);

            $results = [];
            $allWritable = true;

            foreach ($paths as $relativePath) {
                $absolute = storage_path($relativePath);

                if (! File::isDirectory($absolute)) {
                    File::makeDirectory($absolute, 0755, true);
                }

                $writable = is_writable($absolute);
                $results[$relativePath] = [
                    'path' => $absolute,
                    'exists' => true,
                    'writable' => $writable,
                ];

                if (! $writable) {
                    $allWritable = false;
                }
            }

            if ($extended) {
                $cachePath = bootstrap_path('cache');
                $results['bootstrap/cache'] = [
                    'path' => $cachePath,
                    'exists' => File::isDirectory($cachePath),
                    'writable' => is_writable($cachePath),
                ];

                if (! is_writable($cachePath)) {
                    $allWritable = false;
                }
            }

            return [
                'ok' => $allWritable,
                'status' => $allWritable ? 'ok' : 'error',
                'message' => $allWritable ? 'Storage directories writable' : 'One or more storage paths are not writable',
                'details' => $results,
            ];
        }, $timeoutMs);
    }
}
