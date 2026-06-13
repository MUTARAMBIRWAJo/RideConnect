<?php

namespace App\Services\Health\Checks;

use Illuminate\Support\Facades\File;

class ApplicationHealthCheck
{
    /**
     * @return array<string, mixed>
     */
    public function check(bool $extended = false): array
    {
        return \App\Services\HealthCheckService::timed(function () use ($extended) {
            $details = [
                'laravel_boot' => app()->isBooted(),
                'environment' => app()->environment(),
                'debug' => (bool) config('app.debug'),
            ];

            if ($extended) {
                $configCached = File::exists(base_path('bootstrap/cache/config.php'));
                $routesCached = File::exists(base_path('bootstrap/cache/routes-v7.php'))
                    || File::exists(base_path('bootstrap/cache/routes.php'));

                $details['config_cached'] = $configCached;
                $details['routes_cached'] = $routesCached;
                $details['optimize_hint'] = app()->environment('production') && (! $configCached || ! $routesCached)
                    ? 'Run php artisan config:cache && php artisan route:cache in production'
                    : null;
            }

            $ok = (bool) ($details['laravel_boot'] ?? false);

            return [
                'ok' => $ok,
                'status' => $ok ? 'ok' : 'error',
                'message' => $ok ? 'Laravel application booted' : 'Application failed to boot',
                'details' => $details,
            ];
        }, 1000);
    }
}
