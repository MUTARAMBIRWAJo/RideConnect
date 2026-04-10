<?php

namespace App\Filament\Resources\TripResource\Pages;

use App\Filament\Resources\TripResource;
use App\Models\Booking;
use App\Models\Ride;
use App\Services\RuraTariffService;
use Filament\Resources\Pages\CreateRecord;

class CreateTrip extends CreateRecord
{
    protected static string $resource = TripResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! empty($data['fare']) && (float) $data['fare'] > 0) {
            return $data;
        }

        if (! empty($data['booking_id'])) {
            $booking = Booking::query()->find((int) $data['booking_id']);

            if ($booking && (float) $booking->total_price > 0) {
                $data['fare'] = (float) $booking->total_price;

                return $data;
            }
        }

        if (! empty($data['ride_id'])) {
            $ride = Ride::query()->find((int) $data['ride_id']);

            if ($ride) {
                $tariff = app(RuraTariffService::class)->lookupTariff(
                    null,
                    $ride->origin_address,
                    $ride->destination_address,
                    $ride->corridor?->name
                );

                $data['fare'] = (float) ($tariff['fare_rwf'] ?? $ride->price_per_seat ?? 0);
            }
        }

        if (empty($data['fare']) || (float) $data['fare'] <= 0) {
            $data['fare'] = 0;
        }

        return $data;
    }
}
