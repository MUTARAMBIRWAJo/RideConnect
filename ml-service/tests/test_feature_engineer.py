from app.services.feature_engineer import build_feature_vector


def test_build_vector_basic():
    ride = {"pickup_latitude": -1.95, "pickup_longitude": 30.06, "pickup_heading": 0}
    driver = {"current_latitude": -1.95, "current_longitude": 30.06, "driver_rating": 4.8, "acceptance_rate": 0.9, "cancellation_rate": 0.01, "behavior_score": 0.9, "available_seats": 4, "current_heading": 0}
    vec = build_feature_vector(ride, driver)
    assert isinstance(vec, list)
    assert len(vec) == 7
