<?php

namespace App\Jobs;

use App\Services\MlService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PollDemandPredictionsJob implements ShouldQueue
{
    use Queueable;

    /**
     * @var array<int, array{name: string, latitude: float, longitude: float}>
     */
    private array $majorZones = [
        ['name' => 'Remera', 'latitude' => -1.9579, 'longitude' => 30.1127],
        ['name' => 'Nyabugogo', 'latitude' => -1.9398, 'longitude' => 30.0445],
        ['name' => 'Kimironko', 'latitude' => -1.9367, 'longitude' => 30.1304],
        ['name' => 'Kacyiru', 'latitude' => -1.9360, 'longitude' => 30.0820],
        ['name' => 'Kigali Heights', 'latitude' => -1.9536, 'longitude' => 30.0606],
    ];

    public function handle(MlService $mlService): void
    {
        $now = now();

        foreach ($this->majorZones as $zone) {
            $payload = [
                'latitude' => $zone['latitude'],
                'longitude' => $zone['longitude'],
                'hour' => $now->hour,
                'day_of_week' => $now->dayOfWeek,
            ];

            $result = $mlService->predictDemand($payload);

            if (! ($result['success'] ?? false)) {
                Log::warning('Scheduled ML demand prediction failed', [
                    'zone' => $zone['name'],
                    'status' => $result['status'] ?? null,
                    'error' => $result['error'] ?? null,
                ]);
            }
        }
    }
}
