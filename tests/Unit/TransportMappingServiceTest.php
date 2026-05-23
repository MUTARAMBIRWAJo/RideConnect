<?php

namespace Tests\Unit;

use App\Models\Ride;
use App\Services\TransportMappingService;
use PHPUnit\Framework\TestCase;

class TransportMappingServiceTest extends TestCase
{
    public function test_vehicle_to_transport_mapping_is_correct()
    {
        // Bus-type vehicles
        $this->assertEquals(Ride::TRANSPORT_BUS, TransportMappingService::toTransportType('van'));
        $this->assertEquals(Ride::TRANSPORT_BUS, TransportMappingService::toTransportType('bus'));
        $this->assertEquals(Ride::TRANSPORT_BUS, TransportMappingService::toTransportType('minibus'));
        $this->assertEquals(Ride::TRANSPORT_BUS, TransportMappingService::toTransportType('coach'));

        // Car-type vehicles
        $this->assertEquals(Ride::TRANSPORT_CAR, TransportMappingService::toTransportType('sedan'));
        $this->assertEquals(Ride::TRANSPORT_CAR, TransportMappingService::toTransportType('suv'));
        $this->assertEquals(Ride::TRANSPORT_CAR, TransportMappingService::toTransportType('hatchback'));
        $this->assertEquals(Ride::TRANSPORT_CAR, TransportMappingService::toTransportType('compact'));

        // Motorcycle-type vehicles
        $this->assertEquals(Ride::TRANSPORT_MOTORCYCLE, TransportMappingService::toTransportType('motorbike'));
        $this->assertEquals(Ride::TRANSPORT_MOTORCYCLE, TransportMappingService::toTransportType('motorcycle'));
        $this->assertEquals(Ride::TRANSPORT_MOTORCYCLE, TransportMappingService::toTransportType('boda'));
        $this->assertEquals(Ride::TRANSPORT_MOTORCYCLE, TransportMappingService::toTransportType('moto'));
    }

    public function test_mapping_service_is_case_insensitive()
    {
        $this->assertEquals(Ride::TRANSPORT_BUS, TransportMappingService::toTransportType('VAN'));
        $this->assertEquals(Ride::TRANSPORT_BUS, TransportMappingService::toTransportType('Van'));
        $this->assertEquals(Ride::TRANSPORT_CAR, TransportMappingService::toTransportType('SEDAN'));
        $this->assertEquals(Ride::TRANSPORT_CAR, TransportMappingService::toTransportType('Sedan'));
        $this->assertEquals(Ride::TRANSPORT_MOTORCYCLE, TransportMappingService::toTransportType('MOTORBIKE'));
        $this->assertEquals(Ride::TRANSPORT_MOTORCYCLE, TransportMappingService::toTransportType('Motorbike'));
    }

    public function test_unknown_vehicle_type_returns_null()
    {
        $this->assertNull(TransportMappingService::toTransportType('unknown_vehicle'));
        $this->assertNull(TransportMappingService::toTransportType(null));
        $this->assertNull(TransportMappingService::toTransportType(''));
    }

    public function test_is_compatible_returns_true_for_matching_types()
    {
        $this->assertTrue(TransportMappingService::isCompatible('van', Ride::TRANSPORT_BUS));
        $this->assertTrue(TransportMappingService::isCompatible('sedan', Ride::TRANSPORT_CAR));
        $this->assertTrue(TransportMappingService::isCompatible('motorbike', Ride::TRANSPORT_MOTORCYCLE));
    }

    public function test_is_compatible_returns_false_for_mismatched_types()
    {
        $this->assertFalse(TransportMappingService::isCompatible('van', Ride::TRANSPORT_CAR));
        $this->assertFalse(TransportMappingService::isCompatible('sedan', Ride::TRANSPORT_BUS));
        $this->assertFalse(TransportMappingService::isCompatible('motorbike', Ride::TRANSPORT_BUS));
        $this->assertFalse(TransportMappingService::isCompatible('motorbike', Ride::TRANSPORT_CAR));
    }

    public function test_is_compatible_is_case_insensitive()
    {
        $this->assertTrue(TransportMappingService::isCompatible('VAN', Ride::TRANSPORT_BUS));
        $this->assertTrue(TransportMappingService::isCompatible('sedan', Ride::TRANSPORT_CAR));
        $this->assertTrue(TransportMappingService::isCompatible('MOTORBIKE', Ride::TRANSPORT_MOTORCYCLE));
    }

    public function test_is_compatible_returns_false_for_null_values()
    {
        $this->assertFalse(TransportMappingService::isCompatible(null, Ride::TRANSPORT_BUS));
        $this->assertFalse(TransportMappingService::isCompatible('van', null));
        $this->assertFalse(TransportMappingService::isCompatible(null, null));
    }

    public function test_normalize_converts_to_lowercase_and_trims()
    {
        $this->assertEquals('van', TransportMappingService::normalize('VAN'));
        $this->assertEquals('van', TransportMappingService::normalize(' van '));
        $this->assertEquals('van', TransportMappingService::normalize('  VAN  '));
        $this->assertEquals('sedan', TransportMappingService::normalize('SEDAN'));
    }

    public function test_get_vehicle_types_for_transport_type()
    {
        $busVehicles = TransportMappingService::getVehicleTypesFor(Ride::TRANSPORT_BUS);
        $this->assertContains('van', $busVehicles);
        $this->assertContains('bus', $busVehicles);
        $this->assertContains('minibus', $busVehicles);

        $carVehicles = TransportMappingService::getVehicleTypesFor(Ride::TRANSPORT_CAR);
        $this->assertContains('sedan', $carVehicles);
        $this->assertContains('suv', $carVehicles);
        $this->assertContains('hatchback', $carVehicles);

        $motorcycleVehicles = TransportMappingService::getVehicleTypesFor(Ride::TRANSPORT_MOTORCYCLE);
        $this->assertContains('motorbike', $motorcycleVehicles);
        $this->assertContains('motorcycle', $motorcycleVehicles);
        $this->assertContains('boda', $motorcycleVehicles);
    }

    public function test_get_all_vehicle_types()
    {
        $allVehicles = TransportMappingService::getAllVehicleTypes();

        $this->assertContains('van', $allVehicles);
        $this->assertContains('sedan', $allVehicles);
        $this->assertContains('motorbike', $allVehicles);
        $this->assertGreaterThan(6, count($allVehicles));
    }

    public function test_get_all_transport_types()
    {
        $allTransports = TransportMappingService::getAllTransportTypes();

        $this->assertContains(Ride::TRANSPORT_BUS, $allTransports);
        $this->assertContains(Ride::TRANSPORT_CAR, $allTransports);
        $this->assertContains(Ride::TRANSPORT_MOTORCYCLE, $allTransports);
        $this->assertEquals(3, count($allTransports));
    }
}
