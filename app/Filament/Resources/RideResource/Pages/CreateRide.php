<?php

namespace App\Filament\Resources\RideResource\Pages;

use App\Domain\Ride\RidePolicy;
use App\Filament\Resources\RideResource;
use App\Models\TransportRoute;
use Filament\Resources\Pages\CreateRecord;

class CreateRide extends CreateRecord
{
    protected static string $resource = RideResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->applyBusRouteData($data);
    }

    private function applyBusRouteData(array $data): array
    {
        if (($data['transport_type'] ?? null) !== 'BUS') {
            return $data;
        }

        $route = ! empty($data['route_id']) ? TransportRoute::query()->with('corridor')->find((int) $data['route_id']) : null;

        if ($route) {
            $data['corridor_id'] = $route->corridor_id;
            $data['origin_address'] = $route->origin;
            $data['destination_address'] = $route->destination;
            $data['bus_number'] = $route->route_code;
            $data['travel_mode'] = 'SCHEDULED';
        }

        $ride = new \App\Models\Ride($data);
        RidePolicy::assertBusRules($ride);

        return $data;
    }
}
