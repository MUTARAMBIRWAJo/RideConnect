<?php

namespace App\Services\Ml;

use App\Enums\UserRole;
use App\Models\DriverLocation;
use App\Models\User;
use App\Services\MlService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class MlAnomalyDetectionService
{
    private const SPEED_SPIKE_KMH = 100.0;
    private const ACCELERATION_SPIKE_MS2 = 5.0;
    private const HEADING_SPIKE_DEGREES = 120.0;
    private const ROUTE_DEVIATION_METERS = 500.0;
    private const IDLE_SECONDS = 1200;

    public function __construct(private readonly MlService $mlService)
    {
    }

    public function inspectLocationUpdate(
        int $driverId,
        DriverLocation $location,
        ?DriverLocation $previousLocation = null,
        ?float $routeDeviationMeters = null,
        ?int $tripId = null,
    ): ?array {
        $speedKmh = (float) ($location->speed_kmh ?? 0);
        $acceleration = $this->accelerationMs2($previousLocation, $location);
        $headingChange = $this->headingChangeDegrees($previousLocation, $location);
        $routeDeviation = (float) ($routeDeviationMeters ?? 0);
        $idleSeconds = $this->idleSeconds($driverId, $speedKmh);

        if (! $this->shouldCallDetector($speedKmh, $acceleration, $headingChange, $routeDeviation, $idleSeconds)) {
            return null;
        }

        $payload = [
            'speed_kmh' => round($speedKmh, 2),
            'acceleration_ms2' => round($acceleration, 2),
            'heading_change_degrees' => round($headingChange, 1),
            'route_deviation_meters' => round($routeDeviation, 1),
            'stop_duration_seconds' => $idleSeconds,
        ];

        $result = $this->mlService->detectAnomaly($payload, $tripId);
        $data = $result['data']['data'] ?? $result['data'] ?? [];

        if (($data['severity'] ?? null) === 'high') {
            $this->notifyAdmins($driverId, $tripId, $payload, $data);
        }

        return $result;
    }

    private function shouldCallDetector(
        float $speedKmh,
        float $acceleration,
        float $headingChange,
        float $routeDeviation,
        int $idleSeconds,
    ): bool {
        return $speedKmh >= self::SPEED_SPIKE_KMH
            || $acceleration >= self::ACCELERATION_SPIKE_MS2
            || $headingChange >= self::HEADING_SPIKE_DEGREES
            || $routeDeviation >= self::ROUTE_DEVIATION_METERS
            || $idleSeconds >= self::IDLE_SECONDS;
    }

    private function accelerationMs2(?DriverLocation $previousLocation, DriverLocation $location): float
    {
        if (! $previousLocation?->updated_at || ! $location->updated_at) {
            return 0.0;
        }

        $seconds = max(1, $previousLocation->updated_at->diffInSeconds($location->updated_at));
        $previousMs = ((float) ($previousLocation->speed_kmh ?? 0)) / 3.6;
        $currentMs = ((float) ($location->speed_kmh ?? 0)) / 3.6;

        return max(0.0, abs($currentMs - $previousMs) / $seconds);
    }

    private function headingChangeDegrees(?DriverLocation $previousLocation, DriverLocation $location): float
    {
        if ($previousLocation?->heading === null || $location->heading === null) {
            return 0.0;
        }

        $diff = abs((float) $location->heading - (float) $previousLocation->heading);

        return min($diff, 360.0 - $diff);
    }

    private function idleSeconds(int $driverId, float $speedKmh): int
    {
        $cacheKey = "ml:driver-idle-since:{$driverId}";

        if ($speedKmh > 1.0) {
            Cache::forget($cacheKey);

            return 0;
        }

        $idleSince = Cache::remember($cacheKey, now()->addHours(2), fn () => now()->timestamp);

        return max(0, now()->timestamp - (int) $idleSince);
    }

    private function notifyAdmins(int $driverId, ?int $tripId, array $input, array $output): void
    {
        try {
            $admins = User::query()
                ->whereIn('role', [
                    UserRole::SUPER_ADMIN->value,
                    UserRole::ADMIN->value,
                    UserRole::OFFICER->value,
                ])
                ->get();

            if ($admins->isEmpty()) {
                return;
            }

            Notification::make()
                ->title('High severity driving anomaly')
                ->body("Driver {$driverId} triggered the ML anomaly detector.")
                ->danger()
                ->data([
                    'driver_id' => $driverId,
                    'trip_id' => $tripId,
                    'input' => $input,
                    'output' => $output,
                ])
                ->sendToDatabase($admins);
        } catch (Throwable $exception) {
            Log::warning('Failed to notify admins about ML driving anomaly', [
                'driver_id' => $driverId,
                'trip_id' => $tripId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
