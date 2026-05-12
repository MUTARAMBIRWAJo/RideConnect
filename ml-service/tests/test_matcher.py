from app.services.matcher import match_drivers


def test_matcher_basic():
    ride = {"pickup_latitude": -1.95, "pickup_longitude": 30.06}
    drivers = [
        {"driver_id": 1, "current_latitude": -1.95, "current_longitude": 30.06, "driver_rating": 4.9, "acceptance_rate": 0.95, "cancellation_rate": 0.01, "behavior_score": 0.9, "current_heading": 0},
        {"driver_id": 2, "current_latitude": -1.96, "current_longitude": 30.07, "driver_rating": 4.2, "acceptance_rate": 0.6, "cancellation_rate": 0.05, "behavior_score": 0.6, "current_heading": 180},
    ]
    ranked = match_drivers(ride, drivers)
    assert ranked[0]["driver_id"] == 1
