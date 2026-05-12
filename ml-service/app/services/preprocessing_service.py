"""Feature engineering and preprocessing service"""
import numpy as np

from app.core.config import settings
from app.core.feature_config import FEATURE_COLUMNS, validate_feature_order
from app.core.logging import get_logger
from app.core.scaler_manager import get_scaler_manager
from app.schemas.match_request import CandidateDriver, RideRequest
from app.utils.distance import haversine_distance, normalize_distance
from app.utils.similarity import normalize_value

logger = get_logger(__name__)


class PreprocessingService:
    """Handles feature preprocessing and normalization"""
    
    def __init__(self):
        """Initialize preprocessing service"""
        self.distance_max = settings.DISTANCE_MAX_KM
        self.rating_min = settings.RATING_MIN
        self.rating_max = settings.RATING_MAX
        self.acceptance_rate_max = settings.ACCEPTANCE_RATE_MAX
        self.cancellation_rate_max = settings.CANCELLATION_RATE_MAX
        self.behavior_score_max = settings.BEHAVIOR_SCORE_MAX
        self.traffic_level_max = settings.TRAFFIC_LEVEL_MAX
    
    def preprocess_driver_features(
        self, driver: CandidateDriver
    ) -> np.ndarray:
        """
        Build raw driver features in the exact training order.
        
        Args:
            driver: CandidateDriver object
        
        Returns:
            Raw feature vector to be scaled by the trained scaler
        """
        features = np.array([
            driver.distance_km,
            driver.driver_rating,
            driver.acceptance_rate,
            driver.cancellation_rate,
            driver.behavior_score,
            float(driver.available_seats),
            driver.traffic_level,
            driver.direction_similarity,
        ], dtype=np.float32)
        
        return features
    
    def preprocess_request_features(
        self, ride_request: RideRequest
    ) -> np.ndarray:
        """
        Preprocess ride request features
        
        Args:
            ride_request: RideRequest object
        
        Returns:
            Feature vector
        """
        features = np.array([
            ride_request.pickup_latitude,
            ride_request.pickup_longitude,
            ride_request.destination_latitude,
            ride_request.destination_longitude,
            float(ride_request.required_seats),
        ], dtype=np.float32)
        
        return features


class FeatureEngineeringService:
    """Handles feature engineering and calculation"""
    
    def __init__(self):
        """Initialize feature engineering service"""
        self.preprocessing = PreprocessingService()
    
    def calculate_seat_compatibility(
        self,
        available_seats: int,
        required_seats: int
    ) -> float:
        """
        Calculate seat compatibility score
        
        Args:
            available_seats: Available seats in vehicle
            required_seats: Required seats for trip
        
        Returns:
            Compatibility score (0-1)
        """
        if available_seats < required_seats:
            return 0.0
        
        # Penalize excess seats (prefer exact match)
        excess = available_seats - required_seats
        return max(0.5, 1.0 - (excess * 0.1))
    
    def calculate_vehicle_compatibility(
        self,
        driver_vehicle_type: str,
        requested_vehicle_type: str
    ) -> float:
        """
        Calculate vehicle type compatibility
        
        Args:
            driver_vehicle_type: Driver's vehicle type
            requested_vehicle_type: Requested vehicle type
        
        Returns:
            Compatibility score (0-1)
        """
        if driver_vehicle_type.lower() == requested_vehicle_type.lower():
            return 1.0
        
        # Allow some flexibility with vehicle types
        compatible_types = {
            "car": ["sedan", "suv", "hatchback"],
            "motorbike": ["bike", "motorcycle"],
            "taxi": ["car", "sedan"],
        }
        
        driver_base = driver_vehicle_type.lower()
        requested_base = requested_vehicle_type.lower()
        
        if driver_base in compatible_types:
            if requested_base in compatible_types[driver_base]:
                return 0.8
        
        if requested_base in compatible_types:
            if driver_base in compatible_types[requested_base]:
                return 0.8
        
        return 0.3
    
    def engineer_features(
        self,
        driver: CandidateDriver,
        ride_request: RideRequest
    ) -> np.ndarray:
        """
        Engineer comprehensive feature vector
        
        Args:
            driver: CandidateDriver object
            ride_request: RideRequest object
        
        Returns:
            Engineered feature vector
        """
        # Preprocess driver features
        driver_features = self.preprocessing.preprocess_driver_features(driver)
        
        # Calculate compatibility scores
        seat_compat = self.calculate_seat_compatibility(
            driver.available_seats,
            ride_request.required_seats
        )
        
        vehicle_compat = self.calculate_vehicle_compatibility(
            "car",  # Default vehicle type (would come from driver profile in production)
            ride_request.requested_vehicle_type
        )
        
        # Combine all features in exact training order
        engineered_features = np.concatenate([
            driver_features,
            [seat_compat, vehicle_compat],
        ], dtype=np.float32)

        validate_feature_order(FEATURE_COLUMNS)
        
        return engineered_features

    def scale_features(self, feature_batch: np.ndarray) -> np.ndarray:
        """Scale a feature batch with the trained scaler."""
        scaler_manager = get_scaler_manager()
        return scaler_manager.transform(feature_batch)
