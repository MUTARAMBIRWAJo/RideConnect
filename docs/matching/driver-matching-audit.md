# RideConnect Driver Matching Audit

## Matching Pipeline Audited

- `TripMatchingEngineV3` starts V3 matching, retries strict matching for up to 60 seconds, then uses fallback matching.
- `ProcessTripMatchingV3` runs `TripMatchingEngineV3::executeMatch()` from the queue.
- `DriverAvailabilityServiceV3` is the strict V3 eligibility query.
- `DriverMatchingEngineV3` is an older V3 nearby-location broadcaster using `driver_locations_v3`.
- `MatchingService` powers motorcycle trip matching and ML `/match` fallback.
- `DriverMatchingService` powers the mobile driver selection/session flow for `private_car` and `motor_vehicle`.
- `DriverLocationService` updates `driver_locations` and marks stale locations offline.
- `DriverRankerService` optionally calls ML `/ml/rank-drivers` for trip/ride ranking and falls back to distance.

## Mandatory Criteria

- `drivers.status = approved`.
- `drivers.is_active = true` for V3 strict matching.
- `drivers.is_online = true`.
- `drivers.availability_status IN (online, available)`.
- `drivers.current_trip_id IS NULL`.
- `drivers.last_seen_at >= now() - 30 seconds` for V3 strict matching.
- `drivers.last_seen_at >= now() - 15 minutes` for legacy motorcycle matching.
- Driver user account is approved: `users.is_approved = true`.
- Driver has current coordinates in `drivers.current_latitude/current_longitude` or `driver_locations`.
- Driver has an active compatible vehicle.
- Driver has no active `trips` in `PENDING`, `ACCEPTED`, `STARTED`.
- Driver has no active `motorcycle_trips` in `ASSIGNED`, `DRIVER_ASSIGNED`, `PASSENGER_WAITING`, `IN_PROGRESS`.
- Driver has no pending/notified `trip_assignment_attempts` lock.
- Driver is inside the strict search radius when a radius is applied.

## Optional Criteria

- `users.is_verified = true`.
- Vehicle has `verified_at`.
- `driver_locations.is_online = true`.
- `driver_locations.last_activity_at` is fresh.
- `driver_availability_cache` and `driver_availability_snapshots` agree with the live driver row.

## Transport Compatibility

- `private_car` maps to `CAR`: `sedan`, `suv`, `hatchback`, `compact`.
- `motor_vehicle` maps to `MOTORCYCLE`: `motorbike`, `motorcycle`, `boda`, `moto`, `tuk-tuk`, `tricycle`.
- `public_bus` maps to `BUS`: `van`, `bus`, `minibus`, `coach`, `minivan`.

## Scoring Criteria

- V3 strict path ranks by Haversine distance only.
- Legacy fast local matching uses 80% distance score and 20% rating score.
- Legacy ML `/match` payload includes distance, ETA, availability, rating, estimated fare, candidate list, and explicit weights: distance 0.4, ETA 0.3, availability 0.2, rating 0.1.
- `DriverRankerService` ML ranking sends distance, rating, acceptance rate, and vehicle type; fallback is nearest non-test driver.

## Common Rejection Reasons

- Driver is offline or `availability_status` is not `online`/`available`.
- User account is not approved.
- Driver has no active compatible vehicle.
- Vehicle is not active or uses an incompatible `vehicle_type`.
- Missing driver coordinates.
- Heartbeat is stale.
- Active trip or active motorcycle trip exists.
- Pending assignment attempt lock exists.
- Driver is outside search radius.
- V3 strict matching can fail outright if `drivers.is_active` is absent from the database.

## Commands

```bash
php artisan matching:audit
php artisan matching:audit --transport=private_car --lat=-1.94995 --lng=30.11273 --radius=5
php artisan db:seed --class="Database\\Seeders\\Matching\\PremiumKigaliDriverSeeder"
php artisan matching:test --count=100 --transport=private_car
```

## Recommended Pickup Locations

| Location | District | Latitude | Longitude | Estimated nearby seeded drivers |
|---|---:|---:|---:|---:|
| Kigali CBD - City Tower | Nyarugenge | -1.94407 | 30.06188 | 5 |
| Nyabugogo Taxi Park | Nyarugenge | -1.93918 | 30.04454 | 5 |
| Remera Giporoso | Gasabo | -1.95721 | 30.10933 | 8 |
| Kimironko Market | Gasabo | -1.94995 | 30.11273 | 9 |
| Kacyiru Convention Area | Gasabo | -1.95437 | 30.09292 | 10 |
| Kimironko Bus Park | Gasabo | -1.93698 | 30.13014 | 6 |
| Kicukiro Centre | Kicukiro | -1.97367 | 30.09948 | 7 |
| Kigali International Airport | Kicukiro | -1.96863 | 30.13945 | 5 |
| Gisozi Memorial | Gasabo | -1.92994 | 30.06087 | 5 |
| Nyarutarama MTN Centre | Gasabo | -1.93622 | 30.09158 | 7 |
| Kimisagara Market | Nyarugenge | -1.95672 | 30.04342 | 5 |
| Nyamirambo Stadium | Nyarugenge | -1.97879 | 30.04025 | 3 |
| Kagugu Centre | Gasabo | -1.90844 | 30.09641 | 3 |
| Kibagabaga Hospital | Gasabo | -1.92943 | 30.12310 | 6 |
| Gikondo Expo Grounds | Kicukiro | -1.97194 | 30.06948 | 5 |
| Gatenga Centre | Kicukiro | -1.99254 | 30.10914 | 4 |
| Kabeza Centre | Kicukiro | -1.97784 | 30.12417 | 5 |
| Kacyiru Government Zone | Gasabo | -1.94347 | 30.08870 | 9 |
| University of Rwanda Gikondo | Kicukiro | -1.96255 | 30.07314 | 6 |
| Kigali Heights | Gasabo | -1.95332 | 30.09224 | 10 |

## SQL Verification Queries

```sql
select count(*) as premium_seeded_drivers
from users u
join drivers d on d.user_id = u.id
join vehicles v on v.driver_id = d.id
where u.email like 'premium.driver.%@rideconnect.local'
  and u.is_approved = true
  and u.is_verified = true
  and d.status = 'approved'
  and d.is_online = true
  and d.availability_status in ('online', 'available')
  and d.current_trip_id is null
  and d.current_latitude is not null
  and d.current_longitude is not null
  and d.last_seen_at >= now() - interval '30 seconds'
  and v.is_active = true
  and v.verified_at is not null;

select v.vehicle_type, count(*)
from users u
join drivers d on d.user_id = u.id
join vehicles v on v.driver_id = d.id
where u.email like 'premium.driver.%@rideconnect.local'
group by v.vehicle_type
order by v.vehicle_type;

select count(*) as live_seeded_locations
from users u
join driver_locations dl on dl.driver_id = u.mobile_user_id
where u.email like 'premium.driver.%@rideconnect.local'
  and dl.is_online = true
  and dl.last_activity_at >= now() - interval '5 minutes';

select d.id, u.name, d.availability_status, d.is_online, d.last_seen_at,
       v.make, v.model, v.vehicle_type, dl.latitude, dl.longitude
from users u
join drivers d on d.user_id = u.id
join vehicles v on v.driver_id = d.id
left join driver_locations dl on dl.driver_id = u.mobile_user_id
where u.email like 'premium.driver.%@rideconnect.local'
order by d.id;
```

## Recommendations To Reach Above 95% Match Rate

- Keep `drivers.last_seen_at` updated from every driver heartbeat; V3 strict matching only accepts a 30-second heartbeat.
- Ensure the production database has `drivers.is_active`; the included migration adds it.
- Store live coordinates in both `drivers.current_latitude/current_longitude` and `driver_locations`.
- Keep seeded and real vehicles in canonical compatible types: `sedan`, `suv`, `hatchback`, `compact` for private cars.
- Add a database index for `drivers(status, is_active, is_online, availability_status, last_seen_at)`.
- Add a driver-location freshness dashboard alert when online drivers have stale `driver_locations.last_activity_at`.
- Mark drivers busy only after acceptance, but avoid repeatedly notifying the same driver by using ignored driver IDs and assignment locks.
- Consider increasing V3 strict radius from 5 km to staged 5/7.5/10 km before the 60-second fallback.
- Feed the ML ranker only drivers that have already passed hard eligibility checks.
- Run `matching:audit` after every production seed/import before opening the passenger app for tests.
