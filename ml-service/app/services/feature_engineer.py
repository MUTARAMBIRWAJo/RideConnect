from typing import Dict, Any, List
from app.utils.geo import haversine_distance
from app.core.config import settings


def driver_features_from_row(driver_row: Dict[str, Any]) -> Dict[str, float]:
    """Convert a DB driver row (dict) into normalized feature dict."""
    lat = driver_row.get("current_latitude")
    lon = driver_row.get("current_longitude")
    rating = float(driver_row.get("driver_rating") or 0.0)
    acceptance = float(driver_row.get("acceptance_rate") or 0.0)
    cancellation = float(driver_row.get("cancellation_rate") or 0.0)
    behavior = float(driver_row.get("behavior_score") or 0.0)
    seats = int(driver_row.get("available_seats") or 1)

    return {
        "lat": lat or 0.0,
        "lon": lon or 0.0,
        "rating": rating,
        "acceptance": acceptance,
        "cancellation": cancellation,
        "behavior": behavior,
        "seats": seats,
    }


def ride_features_from_row(ride_row: Dict[str, Any]) -> Dict[str, Any]:
    return {
        "pickup_latitude": ride_row.get("pickup_latitude"),
        "pickup_longitude": ride_row.get("pickup_longitude"),
        "destination_latitude": ride_row.get("destination_latitude"),
        "destination_longitude": ride_row.get("destination_longitude"),
        "requested_vehicle_type": ride_row.get("requested_vehicle_type"),
        "requested_seats": ride_row.get("requested_seats", 1),
        "timestamp": ride_row.get("timestamp"),
    }


def build_feature_vector(ride: Dict[str, Any], driver: Dict[str, Any]) -> List[float]:
    """
    Build numeric feature vector for matching model from ride and driver dicts.
    Order: distance_km, rating, acceptance, cancellation, behavior, direction_sim, seats
    """
    dr = driver_features_from_row(driver)
    rr = ride_features_from_row(ride)

    dist = haversine_distance(rr["pickup_latitude"], rr["pickup_longitude"], dr["lat"], dr["lon"]) if rr["pickup_latitude"] is not None else 999.0

    # compute heading similarity if available
    dir_sim = 0.5
    try:
        from app.utils.geo import cosine_similarity
        dir_sim = cosine_similarity(ride.get("pickup_heading", 0.0), driver.get("current_heading", 0.0))
    except Exception:
        dir_sim = 0.5

    return [
        float(dist),
        float(dr["rating"]),
        float(dr["acceptance"]),
        float(dr["cancellation"]),
        float(dr["behavior"]),
        float(dir_sim),
        float(dr["seats"]),
    ]
