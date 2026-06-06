<?php

namespace App\Http\Resources\Passenger;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_request_id' => $this->id,
            'corridor' => [
                'id' => $this->corridor->id,
                'code' => $this->corridor->corridor_code,
                'name' => $this->corridor->corridor_name,
            ],
            'pickup' => [
                'name' => $this->pickup_location,
                'latitude' => (float) $this->pickup_lat,
                'longitude' => (float) $this->pickup_lng,
            ],
            'dropoff' => [
                'name' => $this->dropoff_location,
                'latitude' => (float) $this->dropoff_lat,
                'longitude' => (float) $this->dropoff_lng,
            ],
            'matched_bus' => [
                'vehicle_id' => $this->matched_vehicle_id,
                'plate_number' => $this->vehicle?->license_plate,
                'capacity' => $this->vehicle?->seats,
                'available_seats' => $this->getAvailableSeats(),
            ],
            'driver' => [
                'id' => $this->matched_driver_id,
                'name' => $this->driver?->user?->name,
            ],
            'distance_to_bus_km' => (float) $this->distance_to_bus_km,
            'bus_eta_minutes' => $this->bus_eta_minutes,
            'trip_distance_km' => (float) $this->trip_distance_km,
            'trip_duration_minutes' => $this->trip_duration_minutes,
            'estimated_fare' => (float) $this->estimated_fare,
            'currency' => $this->currency,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Get available seats for the matched vehicle.
     */
    private function getAvailableSeats(): int
    {
        if (! $this->matched_vehicle_id) {
            return 0;
        }

        $assignment = \App\Models\BusRouteAssignment::query()
            ->where('bus_id', $this->matched_vehicle_id)
            ->where('status', 'active')
            ->latest()
            ->first();

        if (! $assignment || ! $assignment->bus) {
            return 0;
        }

        $totalSeats = $assignment->bus->seats ?? 0;
        $bookedSeats = $assignment->passengerBoardings()->count();

        return max(0, $totalSeats - $bookedSeats);
    }
}
