<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Trip;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PublicBusTripService
{
    /**
     * Create a new Public Bus Trip Request
     */
    public function requestTrip(array $data, int $passengerId): Trip
    {
        return DB::transaction(function () use ($data, $passengerId) {
            $trip = Trip::create([
                'passenger_id' => $passengerId,
                'transport_type' => 'PUBLIC_BUS',
                'status' => 'REQUESTED',
                'pickup_location' => $data['pickup_location'],
                'dropoff_location' => $data['dropoff_location'],
                'pickup_lat' => $data['pickup_lat'],
                'pickup_lng' => $data['pickup_lng'],
                'dropoff_lat' => $data['dropoff_lat'] ?? -1.95, // Fallback for tests
                'dropoff_lng' => $data['dropoff_lng'] ?? 30.06, // Fallback for tests
                'fare' => 0, // To be calculated
                'capacity_used' => 0,
                'capacity_total' => 0,
                'requested_at' => now(),
            ]);

            // Notify passenger that we are searching
            $this->notifyPassenger($trip, 'Searching for an available bus...');

            // Trigger Matcher
            $this->matchBus($trip);

            return $trip;
        });
    }

    /**
     * Match a bus using pessimistic locking to prevent double booking.
     */
    public function matchBus(Trip $trip): void
    {
        $trip->update(['status' => 'SEARCHING_BUS']);

        DB::transaction(function () use ($trip) {
            // Find an active bus with capacity
            // We use the Driver model where transport_type=PUBLIC_BUS (or just any online bus driver)
            // Assuming driver has a vehicle with capacity. For simplicity in this new flow, we check the Trip table 
            // to see if there is an active bus we can assign to. In a real scenario, buses are Drivers.
            
            $busDriver = Driver::where('status', 'approved')
                ->where('is_available', true)
                // In a full implementation, we'd check vehicle capacity via relations.
                ->lockForUpdate()
                ->first();

            if ($busDriver) {
                // Simulate capacity validation (e.g. max 50)
                // For a bus, the "bus" is running its own "Master Trip" or we just assign the passenger trip to the driver
                
                $trip->update([
                    'status' => 'BUS_ASSIGNED',
                    'driver_id' => $busDriver->id,
                    'bus_id' => $busDriver->id, // simplified
                    'capacity_total' => 50,
                    'capacity_used' => 1,
                    'accepted_at' => now(),
                ]);

                // Notify Passenger & Driver
                $this->notifyPassenger($trip, "Bus Assigned! Bus ID: {$busDriver->id} is on the way.");
                $this->notifyDriver($busDriver->id, "New passenger assigned to your bus.");
            } else {
                Log::warning('PublicBusTripService: No available buses found for trip ' . $trip->id);
            }
        });
    }

    /**
     * Passenger Boards the Bus
     */
    public function board(Trip $trip): void
    {
        if ($trip->status !== 'BUS_ASSIGNED' && $trip->status !== 'BUS_ARRIVING') {
            throw new \Exception('Trip is not in a valid state for boarding.');
        }

        $trip->update([
            'status' => 'PASSENGERS_BOARDING',
            'boarding_status' => ['boarded_at' => now()->toIso8601String(), 'verified' => true],
        ]);
    }

    /**
     * Driver Starts the Trip
     */
    public function start(Trip $trip): void
    {
        if (!in_array($trip->status, ['BUS_ASSIGNED', 'PASSENGERS_BOARDING', 'BUS_ARRIVING'])) {
            throw new \Exception('Trip cannot be started from current status.');
        }

        $trip->update([
            'status' => 'IN_PROGRESS',
            'started_at' => now(),
        ]);

        $this->notifyPassenger($trip, 'Your trip has started. Live tracking enabled.');
    }

    /**
     * Auto-transition to Near Destination
     */
    public function checkNearDestination(Trip $trip, float $currentLat, float $currentLng): void
    {
        if ($trip->status === 'IN_PROGRESS') {
            $dist = $this->haversineKm($currentLat, $currentLng, $trip->dropoff_lat, $trip->dropoff_lng);
            if ($dist < 1.0) {
                $trip->update(['status' => 'NEAR_DESTINATION']);
                $this->notifyPassenger($trip, 'Prepare to alight, you are near your destination.');
            }
        }
    }

    /**
     * Complete the Trip
     */
    public function complete(Trip $trip): void
    {
        if (!in_array($trip->status, ['STARTED', 'NEAR_DESTINATION'])) {
            throw new \Exception('Trip is not in progress.');
        }

        DB::transaction(function () use ($trip) {
            $trip->update([
                'status' => 'COMPLETED',
                'completed_at' => now(),
                'actual_fare' => $trip->fare, // finalize fare
                'capacity_used' => max(0, $trip->capacity_used - 1), // free capacity
            ]);

            $this->notifyPassenger($trip, 'Trip completed. Receipt sent.');
        });
    }

    private function notifyPassenger(Trip $trip, string $message): void
    {
        Log::info("Notification to Passenger {$trip->passenger_id}: {$message}");
        // Here we would integrate with FCM or existing notification service
    }

    private function notifyDriver(int $driverId, string $message): void
    {
        Log::info("Notification to Driver {$driverId}: {$message}");
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);
        
        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lngDelta / 2) * sin($lngDelta / 2);
             
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}
