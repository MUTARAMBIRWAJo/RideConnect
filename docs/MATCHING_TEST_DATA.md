# Matching Test Data

Run:

```bash
php artisan db:seed --class=MatchingTestDataSeeder
```

All seeded users use password `Test@12345`.

## Passengers

| Name | Email | Phone | Main test pickup |
| --- | --- | --- | --- |
| Aline Uwase | `test.passenger.aline@rideconnect.local` | `+250788100001` | Kimironko Market |
| Eric Mugisha | `test.passenger.eric@rideconnect.local` | `+250788100002` | Kigali Convention Centre |
| Claudine Ishimwe | `test.passenger.claudine@rideconnect.local` | `+250788100003` | Nyabugogo Bus Park |

## Drivers

| Name | Email | Phone | Type | Plate | Google Maps coordinates |
| --- | --- | --- | --- | --- | --- |
| Jean Nshimiyimana | `test.driver.moto.jean@rideconnect.local` | `+250788200001` | motorcycle | `RCM-101M` | `-1.9472, 30.0631` |
| Patrick Habyarimana | `test.driver.moto.patrick@rideconnect.local` | `+250788200002` | motorcycle | `RCM-102M` | `-1.9548, 30.0922` |
| Sandrine Mukamana | `test.driver.car.sandrine@rideconnect.local` | `+250788200003` | sedan | `RCC-201C` | `-1.9528, 30.0899` |
| Claude Mugenzi | `test.driver.car.claude@rideconnect.local` | `+250788200004` | suv | `RCC-202C` | `-1.9404, 30.0749` |
| Emmanuel Bus | `test.driver.bus.emmanuel@rideconnect.local` | `+250788200005` | van/public bus | `RCB-301B` | `-1.9493, 30.0628` |
| Vestine Transit | `test.driver.bus.vestine@rideconnect.local` | `+250788200006` | van/public bus | `RCB-302B` | `-1.9571, 30.1041` |

## Test Locations

| Location | Coordinates | Suggested radius |
| --- | --- | --- |
| Kimironko Market | `-1.9480, 30.0619` | 5 km |
| Remera Bus Park | `-1.9567, 30.1056` | 5 km |
| Kigali Convention Centre | `-1.9545, 30.0933` | 5 km |
| Kigali International Airport | `-1.9686, 30.1395` | 8 km |
| Nyabugogo Bus Park | `-1.9399, 30.0446` | 5 km |
| Downtown Bus Park | `-1.9536, 30.0600` | 5 km |
| Kacyiru Police Headquarters | `-1.9393, 30.0758` | 5 km |
| Kigali Heights | `-1.9539, 30.0927` | 5 km |

## Matching Flows

Public bus:

```json
{
  "corridor_id": "<id from GET /api/v1/passenger/public-bus/corridors where corridor_code is MATCH-105>",
  "pickup_location": "Kimironko Market",
  "dropoff_location": "Nyabugogo Bus Park"
}
```

Private car matching:

```json
{
  "transport_type": "private_car",
  "pickup_lat": -1.9545,
  "pickup_lng": 30.0933,
  "dropoff_lat": -1.9686,
  "dropoff_lng": 30.1395,
  "limit": 5
}
```

Motorcycle matching:

```json
{
  "pickup_location": "Kimironko Market",
  "pickup_lat": -1.9480,
  "pickup_lng": 30.0619,
  "dropoff_location": "Kigali Convention Centre",
  "dropoff_lat": -1.9545,
  "dropoff_lng": 30.0933
}
```

These records populate both `drivers.current_latitude/current_longitude` and `driver_locations`, so the public bus, private car, and motorcycle matching paths can all see the same online drivers.
