<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PollDemandPredictionsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $service = app(\App\Services\DemandPredictionService::class);
        $points = $service->predict();

        $highDemandZones = [];
        foreach ($points as $point) {
            if (($point['intensity'] ?? 0) >= 0.70) {
                // Find the zone name from DB since the points collection doesn't have it directly.
                $prediction = \App\Models\DemandPrediction::where('lat', $point['lat'])
                    ->where('lng', $point['lng'])
                    ->orderByDesc('predicted_at')
                    ->first();

                if ($prediction) {
                    $highDemandZones[] = [
                        'name' => $prediction->zone_name ?? $prediction->zone_id,
                        'intensity' => $point['intensity']
                    ];
                }
            }
        }

        if (empty($highDemandZones)) {
            Log::info('No high demand zones detected this hour.');
            return;
        }

        // Notify only specific drivers:
        // 1. Public Bus drivers with available seats
        // 2. Motor-drivers with 2 seats available (idle/no active trips)
        $availableDrivers = \App\Models\Driver::with(['user', 'vehicles', 'rides' => function ($query) {
                $query->whereIn('status', ['published', 'in_progress'])
                      ->where('available_seats', '>', 0);
            }])
            ->where('status', 'ONLINE')
            ->where('is_available', true)
            ->get()
            ->filter(function ($driver) {
                $activeVehicle = $driver->vehicles->where('is_active', true)->first();
                if (!$activeVehicle) {
                    return false;
                }

                $type = strtolower($activeVehicle->vehicle_type ?? '');

                // 1. Public Bus with available seats
                if (in_array($type, ['bus', 'public_bus', 'minibus'])) {
                    return $driver->rides->isNotEmpty();
                }

                // 2. Motor-driver with idle of 2 seats available
                if (in_array($type, ['motorcycle', 'moto', 'bike', 'motor_vehicle'])) {
                    $hasActiveTrip = $driver->hasActiveMotoTrip();
                    return !$hasActiveTrip && ((int) $activeVehicle->seats >= 2);
                }

                return false;
            });

        if ($availableDrivers->isEmpty()) {
            return;
        }

        foreach ($highDemandZones as $zone) {
            Log::info("High demand in {$zone['name']}. Notifying {$availableDrivers->count()} targeted drivers.");
            
            $notification = new \App\Notifications\HighDemandZoneNotification(
                $zone['name'], 
                $zone['intensity']
            );

            foreach ($availableDrivers as $driver) {
                if ($driver->user) {
                    $driver->user->notify($notification);
                }
            }
        }
    }
}
