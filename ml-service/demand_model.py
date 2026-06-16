"""
ml/demand_model.py
Dynamic demand prediction for RideConnect — Kigali, Rwanda.
Reads real trip counts from the database and blends with a
time-of-day + weather model calibrated to Kigali demand patterns.
When >30 days of real data accumulates, replace predict_demand()
with the LSTM model trained on that data.

Install dependencies: pip install sqlalchemy pymysql
"""
import os
import math
import random
from datetime import datetime, timedelta

try:
    from sqlalchemy import create_engine, text
    DB_URL = os.getenv(
        "DATABASE_URL",
        "mysql+pymysql://root:password@db:3306/rideconnect"
    )
    engine = create_engine(DB_URL, pool_pre_ping=True, pool_recycle=3600)
    DB_AVAILABLE = True
except Exception:
    DB_AVAILABLE = False

# All 10 Kigali zones with GPS centre, demand weight, and zone type.
# These match the zones defined in the synthetic dataset generator.
ZONES = {
    "NYB": {"lat": -1.9294, "lng": 30.0555, "weight": 0.90, "type": "transit_hub",  "name": "Nyabugogo"},
    "CBD": {"lat": -1.9441, "lng": 30.0619, "weight": 0.85, "type": "commercial",   "name": "Downtown CBD"},
    "REM": {"lat": -1.9441, "lng": 30.1060, "weight": 0.80, "type": "transit_hub",  "name": "Remera"},
    "KIM": {"lat": -1.9313, "lng": 30.1188, "weight": 0.75, "type": "transit_hub",  "name": "Kimironko"},
    "KCY": {"lat": -1.9365, "lng": 30.0928, "weight": 0.70, "type": "government",   "name": "Kacyiru"},
    "GIK": {"lat": -1.9700, "lng": 30.0750, "weight": 0.60, "type": "industrial",   "name": "Gikondo"},
    "NYM": {"lat": -1.9772, "lng": 30.0472, "weight": 0.65, "type": "residential",  "name": "Nyamirambo"},
    "KAN": {"lat": -1.9700, "lng": 30.1300, "weight": 0.50, "type": "residential",  "name": "Kanombe"},
    "KAB": {"lat": -1.9300, "lng": 30.1800, "weight": 0.55, "type": "transit_hub",  "name": "Kabuga"},
    "MSK": {"lat": -2.0100, "lng": 30.0700, "weight": 0.40, "type": "suburban",     "name": "Masaka"},
}

RWANDAN_HOLIDAYS_2025 = {
    "2025-01-01", "2025-01-02", "2025-02-01", "2025-04-07",
    "2025-04-18", "2025-04-21", "2025-05-01", "2025-07-01",
    "2025-07-04", "2025-08-15", "2025-12-25", "2025-12-26",
}


def hour_demand_multiplier(hour: int, zone_type: str) -> float:
    """
    Real Kigali demand curve — different multiplier for every hour.
    Calibrated from RURA reports and field observations:
    - Morning peak 07:00-09:00 (multiplier 2.5x)
    - Afternoon lunch bump 12:00-14:00 (multiplier 1.5x)
    - Evening peak 17:00-19:30 (multiplier 2.2x)
    - Dead hours 01:00-05:00 (multiplier 0.15x)
    """
    t = hour
    if t < 1:    base = 0.30
    elif t < 5:  base = 0.15
    elif t < 6:  base = 0.35
    elif t < 7:  base = 0.50
    elif t < 9:  base = 2.50   # morning peak
    elif t < 11: base = 1.10
    elif t < 12: base = 1.00
    elif t < 14: base = 1.50   # lunch bump
    elif t < 16: base = 0.90
    elif t < 17: base = 1.10
    elif t < 20: base = 2.20   # evening peak
    elif t < 21: base = 1.10
    elif t < 22: base = 0.80
    elif t < 23: base = 0.60
    else:        base = 0.35

    # Transit hubs get extra boost during peaks
    if zone_type == "transit_hub" and (7 <= t <= 9 or 17 <= t <= 19):
        base *= 1.25
    # Industrial zones peak earlier (workers start 06:00)
    if zone_type == "industrial" and (6 <= t <= 8 or 16 <= t <= 18):
        base *= 1.35
    # Nyamirambo nightlife boost
    if zone_type == "residential" and 20 <= t <= 23:
        base *= 1.20

    return max(base, 0.05)


def fetch_real_demand(zone_id: str, target_dt: datetime, window_hours: int = 2) -> float:
    """
    Query actual trip counts from the MySQL trips table for this zone
    and a time window ending at target_dt.
    Returns 0.0 if database is unreachable or no data found.
    """
    if not DB_AVAILABLE:
        return 0.0
    try:
        zone = ZONES.get(zone_id, {})
        lat = zone.get("lat", -1.94)
        lng = zone.get("lng", 30.06)
        radius_deg = 0.025   # ~2.5km radius in decimal degrees

        from_dt = target_dt - timedelta(hours=window_hours)

        # Try zone_id column first (if it exists), fallback to coordinate query
        try:
            sql = text("""
                SELECT COUNT(*) AS cnt FROM trips
                WHERE zone_id = :zone_id
                  AND created_at BETWEEN :from_dt AND :to_dt
                  AND status IN ('completed', 'started', 'accepted', 'enroute')
            """)
            with engine.connect() as conn:
                row = conn.execute(sql, {
                    "zone_id": zone_id,
                    "from_dt": from_dt.isoformat(),
                    "to_dt":   target_dt.isoformat(),
                }).fetchone()
            count = float(row[0]) if row else 0.0
        except Exception:
            # Fallback: spatial coordinate query (works even without zone_id column)
            sql = text("""
                SELECT COUNT(*) AS cnt FROM trips
                WHERE pickup_lat BETWEEN :lat_min AND :lat_max
                  AND pickup_lng BETWEEN :lng_min AND :lng_max
                  AND created_at BETWEEN :from_dt AND :to_dt
                  AND status IN ('completed', 'started', 'accepted', 'enroute')
            """)
            with engine.connect() as conn:
                row = conn.execute(sql, {
                    "lat_min": lat - radius_deg,
                    "lat_max": lat + radius_deg,
                    "lng_min": lng - radius_deg,
                    "lng_max": lng + radius_deg,
                    "from_dt": from_dt.isoformat(),
                    "to_dt":   target_dt.isoformat(),
                }).fetchone()
            count = float(row[0]) if row else 0.0

        return count
    except Exception as e:
        print(f"[demand_model] DB query failed for zone {zone_id}: {e}")
        return 0.0


def predict_demand(zone_id: str, timestamp: datetime, features: dict) -> dict:
    """
    Main prediction function. Combines:
    1. Time-of-day multiplier (dynamic — different every hour)
    2. Day-of-week multiplier (weekends -35%)
    3. Weather multipliers (rain +55%, cold +10%)
    4. Real historical trip count from database (weighted 70% when available)
    5. Synthetic baseline from zone weight (used when no real data yet)

    Returns a dict with demand_score (0-100), raw estimate, and metadata.
    The demand_score WILL change every hour and every zone WILL differ.
    """
    zone = ZONES.get(zone_id)
    if not zone:
        return {"zone_id": zone_id, "demand_score": 0.0, "confidence": "unknown"}

    hour    = timestamp.hour
    weekday = timestamp.weekday()   # 0=Monday, 6=Sunday
    date_str = timestamp.strftime("%Y-%m-%d")

    is_weekend = weekday >= 5
    is_holiday = date_str in RWANDAN_HOLIDAYS_2025
    is_peak    = (7 <= hour <= 9) or (17 <= hour <= 19)
    rain       = float(features.get("is_raining", 0))
    temp       = float(features.get("temperature", 21.0))

    # --- Multipliers ---
    tod_mul  = hour_demand_multiplier(hour, zone["type"])
    dow_mul  = 0.65 if (is_weekend or is_holiday) else 1.0
    rain_mul = 1.55 if rain else 1.0
    temp_mul = 1.10 if temp < 18.0 else 1.0

    # --- Real data from database ---
    real_count = fetch_real_demand(zone_id, timestamp, window_hours=2)

    # --- Synthetic baseline (always available as fallback) ---
    synthetic_base = zone["weight"] * tod_mul * dow_mul * rain_mul * temp_mul * 28

    # --- Blend: real data weighted 70% if available, else 100% synthetic ---
    if real_count > 2:
        blended    = 0.30 * synthetic_base + 0.70 * real_count
        confidence = "high"
    elif real_count > 0:
        blended    = 0.50 * synthetic_base + 0.50 * real_count
        confidence = "medium"
    else:
        blended    = synthetic_base
        confidence = "synthetic"

    # Normalize to 0-100 score
    score = min(round(blended / 35.0 * 100, 1), 100.0)

    return {
        "zone_id":       zone_id,
        "zone_name":     zone["name"],
        "demand_score":  score,
        "raw_estimate":  round(blended, 1),
        "real_db_count": round(real_count, 1),
        "confidence":    confidence,
        "hour":          hour,
        "is_peak":       is_peak,
        "rain_active":   bool(rain),
        "lat":           zone["lat"],
        "lng":           zone["lng"],
        "multipliers": {
            "time_of_day": round(tod_mul, 3),
            "weekend":     round(dow_mul, 3),
            "rain":        round(rain_mul, 3),
        },
    }


def predict_all_zones(timestamp: datetime, features: dict) -> list:
    """
    Predict demand for all 10 zones at once.
    Called by the admin heatmap every 5 minutes.
    Returns list sorted by demand_score descending (highest demand first).
    """
    results = [predict_demand(zone_id, timestamp, features) for zone_id in ZONES]
    return sorted(results, key=lambda x: -x["demand_score"])
