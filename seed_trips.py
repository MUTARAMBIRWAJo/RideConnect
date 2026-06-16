"""
seed_trips.py — RideConnect historical data seeder
===================================================
Inserts ~50,000 realistic trip records across March, April, May 2025
into the existing MySQL trips table. Each day has different volumes:
  - Weekday morning peak (07:00-09:00): 3x more trips than average
  - Weekday evening peak (17:00-19:00): 2.8x more trips than average
  - Weekends: 65% of weekday volume
  - Rainy days (March/April): 55% more trips than dry days
  - Rwandan public holidays: 60% of weekday volume

Run: pip install sqlalchemy pymysql
     python seed_trips.py

Adjust DB_URL at the top to match your local Docker connection.
The script inserts in batches of 500 rows to avoid memory issues.
"""
import os
import uuid
import math
import random
from datetime import datetime, timedelta

try:
    from sqlalchemy import create_engine, text
except ImportError:
    print("Run: pip install sqlalchemy pymysql")
    exit(1)

random.seed(42)

# ---- CONFIGURE THIS TO MATCH YOUR DOCKER MYSQL ----
DB_URL = os.getenv(
    "DATABASE_URL",
    "mysql+pymysql://root:password@127.0.0.1:3306/rideconnect"
)
# ---------------------------------------------------

engine = create_engine(DB_URL, pool_pre_ping=True)

ZONES = {
    "NYB": {"lat": -1.9294, "lng": 30.0555, "weight": 0.90, "type": "transit_hub"},
    "CBD": {"lat": -1.9441, "lng": 30.0619, "weight": 0.85, "type": "commercial"},
    "REM": {"lat": -1.9441, "lng": 30.1060, "weight": 0.80, "type": "transit_hub"},
    "KIM": {"lat": -1.9313, "lng": 30.1188, "weight": 0.75, "type": "transit_hub"},
    "KCY": {"lat": -1.9365, "lng": 30.0928, "weight": 0.70, "type": "government"},
    "GIK": {"lat": -1.9700, "lng": 30.0750, "weight": 0.60, "type": "industrial"},
    "NYM": {"lat": -1.9772, "lng": 30.0472, "weight": 0.65, "type": "residential"},
    "KAN": {"lat": -1.9700, "lng": 30.1300, "weight": 0.50, "type": "residential"},
    "KAB": {"lat": -1.9300, "lng": 30.1800, "weight": 0.55, "type": "transit_hub"},
    "MSK": {"lat": -2.0100, "lng": 30.0700, "weight": 0.40, "type": "suburban"},
}
ZONE_KEYS    = list(ZONES.keys())
ZONE_WEIGHTS = [ZONES[z]["weight"] for z in ZONE_KEYS]

HOLIDAYS = {
    "2025-04-07", "2025-05-01",
}


def hour_trips(hour: int, zone_type: str, is_weekend: bool,
               is_holiday: bool, is_raining: bool) -> int:
    """Return trip count to generate for this hour/zone combination."""
    t = hour
    if t < 1:    base = 0.3
    elif t < 5:  base = 0.1
    elif t < 7:  base = 0.5
    elif t < 9:  base = 3.0
    elif t < 12: base = 1.0
    elif t < 14: base = 1.8
    elif t < 17: base = 0.9
    elif t < 20: base = 2.8
    elif t < 22: base = 1.2
    else:        base = 0.4

    if zone_type == "transit_hub" and (7 <= t <= 9 or 17 <= t <= 19):
        base *= 1.3
    if is_weekend or is_holiday:
        base *= 0.65
    if is_raining:
        base *= 1.55

    count = max(int(base * 3 + random.gauss(0, 0.8)), 0)
    return count


def jitter(coord: float, radius: float = 0.02) -> float:
    return coord + random.uniform(-radius, radius)


def fare_for_km(dist_km: float) -> float:
    rate = 59.28
    if dist_km <= 1:   mult = 4.0
    elif dist_km <= 3: mult = 2.0
    elif dist_km <= 5: mult = 1.1
    else:              mult = 0.95
    return round(rate * mult * max(dist_km, 0.5), 0)


def main():
    # Load existing passenger and driver UUIDs
    with engine.connect() as conn:
        passengers = [r[0] for r in conn.execute(
            text("SELECT id FROM users WHERE role='PASSENGER' LIMIT 500")
        ).fetchall()]
        drivers = [r[0] for r in conn.execute(
            text("SELECT id FROM drivers LIMIT 100")
        ).fetchall()]

    if not passengers:
        print("ERROR: No passengers found in the users table.")
        print("Please run your existing user seeder first, then re-run this script.")
        return
    if not drivers:
        print("ERROR: No drivers found in the drivers table.")
        print("Please run your existing driver seeder first, then re-run this script.")
        return

    print(f"Found {len(passengers)} passengers and {len(drivers)} drivers.")
    print("Generating 3 months of trip data (March-May 2025)...")

    batch   = []
    total   = 0
    START   = datetime(2025, 3, 1)
    END     = datetime(2025, 5, 31, 23, 59, 59)
    current = START

    while current <= END:
        date_str   = current.strftime("%Y-%m-%d")
        is_weekend = current.weekday() >= 5
        is_holiday = date_str in HOLIDAYS
        is_raining = random.random() < (0.42 if current.month in (3, 4) else 0.15)

        for hour in range(24):
            for zone_id in ZONE_KEYS:
                zone  = ZONES[zone_id]
                count = hour_trips(
                    hour, zone["type"], is_weekend, is_holiday, is_raining
                )
                for _ in range(count):
                    pickup_lat = round(jitter(zone["lat"]), 6)
                    pickup_lng = round(jitter(zone["lng"]), 6)

                    drop_zone_id = random.choices(ZONE_KEYS, weights=ZONE_WEIGHTS)[0]
                    dz           = ZONES[drop_zone_id]
                    drop_lat     = round(jitter(dz["lat"]), 6)
                    drop_lng     = round(jitter(dz["lng"]), 6)

                    dist_km  = math.sqrt(
                        (pickup_lat - drop_lat) ** 2 + (pickup_lng - drop_lng) ** 2
                    ) * 111.0
                    dist_km  = max(round(dist_km, 2), 0.5)

                    fare_est    = fare_for_km(dist_km)
                    fare_actual = round(fare_est * random.uniform(0.95, 1.15), 0)
                    dur_min     = max(int(dist_km * 3.5 + random.gauss(0, 2)), 3)

                    minute  = random.randint(0, 59)
                    second  = random.randint(0, 59)
                    created = current.replace(
                        hour=hour, minute=minute, second=second
                    )
                    started   = created + timedelta(minutes=random.randint(3, 12))
                    completed = started + timedelta(minutes=dur_min)

                    rnd = random.random()
                    if rnd < 0.78:
                        status = "COMPLETED"
                    elif rnd < 0.96:
                        status = "CANCELLED"
                    else:
                        status = "STARTED"

                    batch.append({
                        "passenger_id":    random.choice(passengers),
                        "driver_id":       random.choice(drivers) if status != "CANCELLED" else None,
                        "pickup_lat":      pickup_lat,
                        "pickup_lng":      pickup_lng,
                        "pickup_location": f"{zone_id} area, Kigali",
                        "dropoff_lat":     drop_lat,
                        "dropoff_lng":     drop_lng,
                        "dropoff_location": f"{drop_zone_id} area, Kigali",
                        "status":          status,
                        "fare":            fare_est,
                        "actual_fare":     fare_actual if status == "COMPLETED" else None,
                        "actual_distance": dist_km,
                        "pickup_zone":     zone_id,
                        "created_at":      created.strftime("%Y-%m-%d %H:%M:%S"),
                        "started_at":      started.strftime("%Y-%m-%d %H:%M:%S")
                                           if status in ("STARTED", "COMPLETED") else None,
                        "completed_at":    completed.strftime("%Y-%m-%d %H:%M:%S")
                                           if status == "COMPLETED" else None,
                    })
                    total += 1

            # Flush every 500 rows
            if len(batch) >= 500:
                with engine.begin() as conn:
                    conn.execute(text("""
                        INSERT INTO trips (
                            passenger_id, driver_id,
                            pickup_lat, pickup_lng, pickup_location,
                            dropoff_lat, dropoff_lng, dropoff_location,
                            status, fare, actual_fare, actual_distance, pickup_zone,
                            created_at, started_at, completed_at
                        ) VALUES (
                            :passenger_id, :driver_id,
                            :pickup_lat, :pickup_lng, :pickup_location,
                            :dropoff_lat, :dropoff_lng, :dropoff_location,
                            :status, :fare, :actual_fare, :actual_distance, :pickup_zone,
                            :created_at, :started_at, :completed_at
                        )
                    """), batch)
                print(f"  {current.strftime('%Y-%m-%d')} — inserted batch, total so far: {total:,}")
                batch = []

        current += timedelta(days=1)

    # Flush remaining rows
    if batch:
        with engine.begin() as conn:
            conn.execute(text("""
                INSERT INTO trips (
                    passenger_id, driver_id,
                    pickup_lat, pickup_lng, pickup_location,
                    dropoff_lat, dropoff_lng, dropoff_location,
                    status, fare, actual_fare, actual_distance, pickup_zone,
                    created_at, started_at, completed_at
                ) VALUES (
                    :passenger_id, :driver_id,
                    :pickup_lat, :pickup_lng, :pickup_location,
                    :dropoff_lat, :dropoff_lng, :dropoff_location,
                    :status, :fare, :actual_fare, :actual_distance, :pickup_zone,
                    :created_at, :started_at, :completed_at
                )
            """), batch)

    print(f"\nDone! Inserted {total:,} trips across March-May 2025.")
    print("The demand prediction heatmap will now show dynamic, changing scores.")
    print("Verify with: SELECT HOUR(created_at) AS h, COUNT(*) FROM trips GROUP BY h ORDER BY h;")
    print("Expected: hour 8 and hours 17-19 should have 3-4x more trips than hour 3.")


if __name__ == "__main__":
    main()
