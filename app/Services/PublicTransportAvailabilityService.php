<?php

namespace App\Services;

use App\Models\Ride;
use App\Models\Trip;
use App\Models\TripAssignmentAttempt;
use Illuminate\Database\Eloquent\Builder;

class PublicTransportAvailabilityService
{
    public function availableQuery(array $filters = []): Builder
    {
        $query = Ride::query()
            ->with(['driver.user', 'driver.vehicles', 'route', 'corridor'])
            ->whereIn('transport_type', [Ride::TRANSPORT_BUS, Ride::TRANSPORT_MOTORCYCLE])
            ->whereIn('status', ['published', 'scheduled', 'PUBLISHED', 'SCHEDULED'])
            ->whereHas('driver', function (Builder $driver): void {
                $driver->where('status', 'approved')
                    ->where('availability_status', 'available');
            })
            ->whereHas('vehicle', function (Builder $vehicle): void {
                $vehicle->where('is_active', true)
                    ->where(function (Builder $q): void {
                        $q->whereNull('maintenance_status')
                            ->orWhere('maintenance_status', 'operational');
                    });
            });

        $query->where(function (Builder $q): void {
            $q->where(function (Builder $bus): void {
                $bus->where('transport_type', Ride::TRANSPORT_BUS)
                    ->where('available_seats', '>', 0)
                    ->whereNotNull('route_id')
                    ->whereHas('route', fn (Builder $route) => $route->where('is_active', true));
            })->orWhere(function (Builder $moto): void {
                $moto->where('transport_type', Ride::TRANSPORT_MOTORCYCLE)
                    ->whereNotIn('driver_id', $this->busyMotoDriverIds())
                    ->whereNotIn('driver_id', $this->activeAssignmentDriverIds());
            });
        });

        if (! empty($filters['transport_type'])) {
            $query->where('transport_type', strtoupper((string) $filters['transport_type']));
        }

        if (! empty($filters['route_id'])) {
            $query->where('route_id', (int) $filters['route_id']);
        }

        return $query->orderBy('departure_time');
    }

    public function list(array $filters = [])
    {
        return $this->availableQuery($filters)->get();
    }

    public function isMotoDriverBusy(int $driverId): bool
    {
        return Trip::query()
            ->where('driver_id', $driverId)
            ->where(function (Builder $query): void {
                $query->whereIn('status', ['ACCEPTED', 'STARTED'])
                    ->orWhereIn('payment_status', ['pending', 'processing']);
            })
            ->exists();
    }

    private function busyMotoDriverIds()
    {
        return Trip::query()
            ->select('driver_id')
            ->whereNotNull('driver_id')
            ->where(function (Builder $query): void {
                $query->whereIn('status', ['ACCEPTED', 'STARTED'])
                    ->orWhereIn('payment_status', ['pending', 'processing']);
            });
    }

    private function activeAssignmentDriverIds()
    {
        return TripAssignmentAttempt::query()
            ->select('driver_id')
            ->where('status', 'pending')
            ->where('expires_at', '>', now());
    }
}
