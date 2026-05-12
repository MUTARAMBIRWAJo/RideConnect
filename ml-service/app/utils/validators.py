"""Utility functions for request validation"""
from typing import Optional

from app.schemas.match_request import RideRequest, CandidateDriver


def validate_coordinates(latitude: float, longitude: float) -> bool:
    """
    Validate geographic coordinates
    
    Args:
        latitude: Latitude value
        longitude: Longitude value
    
    Returns:
        True if valid, False otherwise
    """
    return -90 <= latitude <= 90 and -180 <= longitude <= 180


def validate_ride_request(ride_request: RideRequest) -> tuple[bool, Optional[str]]:
    """
    Validate ride request data
    
    Args:
        ride_request: RideRequest object
    
    Returns:
        Tuple of (is_valid, error_message)
    """
    # Validate pickup coordinates
    if not validate_coordinates(
        ride_request.pickup_latitude, ride_request.pickup_longitude
    ):
        return False, "Invalid pickup coordinates"
    
    # Validate destination coordinates
    if not validate_coordinates(
        ride_request.destination_latitude, ride_request.destination_longitude
    ):
        return False, "Invalid destination coordinates"
    
    # Validate seats
    if ride_request.required_seats < 1:
        return False, "Required seats must be at least 1"
    
    if ride_request.required_seats > 8:
        return False, "Required seats cannot exceed 8"
    
    return True, None


def validate_candidate_driver(driver: CandidateDriver) -> tuple[bool, Optional[str]]:
    """
    Validate candidate driver data
    
    Args:
        driver: CandidateDriver object
    
    Returns:
        Tuple of (is_valid, error_message)
    """
    # Validate distance
    if driver.distance_km < 0:
        return False, f"Distance cannot be negative: {driver.distance_km}"
    
    # Validate rating
    if not (1.0 <= driver.driver_rating <= 5.0):
        return False, f"Driver rating must be between 1.0 and 5.0: {driver.driver_rating}"
    
    # Validate rates
    if not (0 <= driver.acceptance_rate <= 100):
        return False, f"Acceptance rate must be between 0 and 100: {driver.acceptance_rate}"
    
    if not (0 <= driver.cancellation_rate <= 100):
        return False, f"Cancellation rate must be between 0 and 100: {driver.cancellation_rate}"
    
    # Validate behavior score
    if not (0 <= driver.behavior_score <= 100):
        return False, f"Behavior score must be between 0 and 100: {driver.behavior_score}"
    
    # Validate seats
    if driver.available_seats < 1:
        return False, f"Available seats must be at least 1: {driver.available_seats}"
    
    # Validate traffic level
    if not (0.0 <= driver.traffic_level <= 1.0):
        return False, f"Traffic level must be between 0.0 and 1.0: {driver.traffic_level}"
    
    # Validate similarity
    if not (0.0 <= driver.direction_similarity <= 1.0):
        return False, f"Direction similarity must be between 0.0 and 1.0: {driver.direction_similarity}"
    
    return True, None
