<?php

namespace App\Services;

use App\Models\Ride;

class TransportMappingService
{
    /**
     * Map vehicle types to transport types.
     * Each vehicle type maps to one primary transport category.
     *
     * @var array<string, string>
     */
    private static array $vehicleToTransportMap = [
        'van' => Ride::TRANSPORT_BUS,
        'bus' => Ride::TRANSPORT_BUS,
        'minibus' => Ride::TRANSPORT_BUS,
        'coach' => Ride::TRANSPORT_BUS,
        'minivan' => Ride::TRANSPORT_BUS,
        'sedan' => Ride::TRANSPORT_CAR,
        'suv' => Ride::TRANSPORT_CAR,
        'hatchback' => Ride::TRANSPORT_CAR,
        'compact' => Ride::TRANSPORT_CAR,
        'motorbike' => Ride::TRANSPORT_MOTORCYCLE,
        'motorcycle' => Ride::TRANSPORT_MOTORCYCLE,
        'boda' => Ride::TRANSPORT_MOTORCYCLE,
        'moto' => Ride::TRANSPORT_MOTORCYCLE,
        'tuk-tuk' => Ride::TRANSPORT_MOTORCYCLE,
        'tricycle' => Ride::TRANSPORT_MOTORCYCLE,
    ];

    /**
     * Convert vehicle type to transport type constant.
     * Returns null if vehicle type is not recognized.
     *
     * @param string|null $vehicleType
     * @return string|null One of: BUS, CAR, MOTORCYCLE or null
     */
    public static function toTransportType(?string $vehicleType): ?string
    {
        if (!$vehicleType) {
            return null;
        }

        $normalized = self::normalize($vehicleType);

        return self::$vehicleToTransportMap[$normalized] ?? null;
    }

    /**
     * Check if a vehicle type is compatible with a specific transport type.
     *
     * @param string|null $vehicleType
     * @param string|null $transportType
     * @return bool
     */
    public static function isCompatible(?string $vehicleType, ?string $transportType): bool
    {
        if (!$vehicleType || !$transportType) {
            return false;
        }

        $mappedTransport = self::toTransportType($vehicleType);

        return $mappedTransport === $transportType;
    }

    /**
     * Normalize a vehicle type string for lookup.
     * Converts to lowercase and trims whitespace.
     *
     * @param string $vehicleType
     * @return string
     */
    public static function normalize(string $vehicleType): string
    {
        return strtolower(trim($vehicleType));
    }

    /**
     * Get all vehicle types that map to a given transport type.
     *
     * @param string $transportType One of: BUS, CAR, MOTORCYCLE
     * @return array<string>
     */
    public static function getVehicleTypesFor(string $transportType): array
    {
        return array_keys(
            array_filter(
                self::$vehicleToTransportMap,
                fn($value) => $value === $transportType
            )
        );
    }

    /**
     * Get all supported vehicle types.
     *
     * @return array<string>
     */
    public static function getAllVehicleTypes(): array
    {
        return array_keys(self::$vehicleToTransportMap);
    }

    /**
     * Get all transport types.
     *
     * @return array<string>
     */
    public static function getAllTransportTypes(): array
    {
        return array_values(array_unique(self::$vehicleToTransportMap));
    }
}
