<?php

namespace App\Domain\Core;

use App\Exceptions\DomainException;
use ReflectionClass;

class DomainGuard
{
    /**
     * Forbidden direct rule checks that must be expressed via policies.
     * This targets the most common bypass patterns.
     */
    private const FORBIDDEN_PATTERNS = [
        '/\$ride\s*->\s*travel_mode\s*[=!]==?/',
        '/\$vehicle\s*->\s*(type|vehicle_type)\s*[=!]==?/',
    ];

    /**
     * Guard helper that scans caller source and blocks known bypass patterns.
     */
    public static function assertUsingPolicy(string $context): void
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $callerFile = $trace[1]['file'] ?? null;

        if (! $callerFile || ! is_file($callerFile)) {
            return;
        }

        $source = (string) file_get_contents($callerFile);

        foreach (self::FORBIDDEN_PATTERNS as $pattern) {
            if (preg_match($pattern, $source) === 1) {
                throw DomainException::make(
                    'Domain guard violation in '.$context.'. Use RidePolicy/DriverPolicy instead of inline transport checks.',
                    'DOMAIN_GUARD_POLICY_BYPASS'
                );
            }
        }
    }

    /**
     * Enforce policy usage inside a controller class by scanning source code.
     *
     * This is intentionally conservative and only targets known bypass patterns.
     */
    public static function assertControllerUsesPolicies(string $controllerClass, array $methods = []): void
    {
        if (! class_exists($controllerClass)) {
            return;
        }

        $reflection = new ReflectionClass($controllerClass);
        $file = $reflection->getFileName();

        if (! $file || ! is_file($file)) {
            return;
        }

        $source = (string) file_get_contents($file);

        foreach (self::FORBIDDEN_PATTERNS as $pattern) {
            if (preg_match($pattern, $source) === 1) {
                throw DomainException::make(
                    'Controller policy enforcement failed for '.$controllerClass.'. Use RidePolicy/DriverPolicy instead of inline transport checks.',
                    'DOMAIN_GUARD_POLICY_BYPASS'
                );
            }
        }
    }
}
