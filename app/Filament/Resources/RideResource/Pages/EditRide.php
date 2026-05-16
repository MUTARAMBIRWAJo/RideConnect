<?php

namespace App\Filament\Resources\RideResource\Pages;

use App\Domain\Ride\RidePolicy;
use App\Filament\Resources\RideResource;
use App\Models\TransportRoute;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRide extends EditRecord
{
    protected static string $resource = RideResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
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

        $ride = $this->record->fill($data);
        RidePolicy::assertBusRules($ride);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
