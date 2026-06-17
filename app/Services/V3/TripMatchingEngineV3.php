<?php

namespace App\Services\V3;

use App\Models\V3\TripV3;

class TripMatchingEngineV3
{
    private TripLifecycleEngineV3 $lifecycle;

    public function __construct(TripLifecycleEngineV3 $lifecycle)
    {
        $this->lifecycle = $lifecycle;
    }

    public function startMatching(TripV3 $trip): void
    {
        $this->lifecycle->transition($trip, 'searching');

        switch ($trip->transport_type) {
            case 'motor_vehicle':
                $this->matchMotorVehicle($trip);
                break;
            case 'private_car':
                $this->matchPrivateCar($trip);
                break;
            case 'public_bus':
                $this->matchPublicBus($trip);
                break;
        }
    }

    private function matchMotorVehicle(TripV3 $trip): void
    {
        // TODO: Real-time nearest driver matching
        // $driverId = MLNearestDriverMatch::find($trip);
        // if ($driverId) {
        //     $trip->driver_id = $driverId;
        //     $trip->save();
        //     $this->lifecycle->transition($trip, 'assigned');
        // }
    }

    private function matchPrivateCar(TripV3 $trip): void
    {
        // TODO: Category-based matching
    }

    private function matchPublicBus(TripV3 $trip): void
    {
        // TODO: Route-based assignment + capacity handling
    }
}
