"""Input validation and feature drift detection."""

from __future__ import annotations

import math
from typing import Optional

import numpy as np

from app.core.feature_config import FEATURE_BOUNDS
from app.core.logging import get_logger

logger = get_logger(__name__)


class InputValidator:
    """Validates input features for NaN, infinite values, and out-of-range values."""
    
    @staticmethod
    def validate_array(
        features: np.ndarray,
        name: str = "features",
        allow_nan: bool = False,
        allow_inf: bool = False,
    ) -> bool:
        """
        Validate numpy array for valid values.
        
        Args:
            features: Array to validate
            name: Name for logging
            allow_nan: Whether to allow NaN values
            allow_inf: Whether to allow infinite values
            
        Returns:
            True if valid
            
        Raises:
            ValueError: If validation fails
        """
        # Check for NaN
        if np.isnan(features).any():
            if not allow_nan:
                raise ValueError(f"{name} contains NaN values")
            logger.warning(f"{name} contains NaN values (allowed)")
        
        # Check for infinity
        if np.isinf(features).any():
            if not allow_inf:
                raise ValueError(f"{name} contains infinite values")
            logger.warning(f"{name} contains infinite values (allowed)")
        
        # Check for non-finite values (catch-all)
        if not np.isfinite(features).all():
            raise ValueError(f"{name} contains non-finite values")
        
        return True
    
    @staticmethod
    def validate_scalar(
        value: float,
        name: str = "value",
        allow_nan: bool = False,
        allow_inf: bool = False,
    ) -> bool:
        """
        Validate scalar value.
        
        Args:
            value: Value to validate
            name: Name for logging
            allow_nan: Whether to allow NaN
            allow_inf: Whether to allow infinity
            
        Returns:
            True if valid
            
        Raises:
            ValueError: If validation fails
        """
        if math.isnan(value):
            if not allow_nan:
                raise ValueError(f"{name} is NaN")
            logger.warning(f"{name} is NaN (allowed)")
        
        if math.isinf(value):
            if not allow_inf:
                raise ValueError(f"{name} is infinite")
            logger.warning(f"{name} is infinite (allowed)")
        
        if not math.isfinite(value):
            raise ValueError(f"{name} is not finite")
        
        return True
    
    @staticmethod
    def validate_shape(
        features: np.ndarray,
        expected_shape: tuple,
        name: str = "features",
    ) -> bool:
        """
        Validate array shape.
        
        Args:
            features: Array to validate
            expected_shape: Expected shape (with None for flexible dims)
            name: Name for logging
            
        Returns:
            True if valid
            
        Raises:
            ValueError: If shape mismatch
        """
        if features.shape != expected_shape:
            # Check flexible dims (None means any value)
            if not _shapes_compatible(features.shape, expected_shape):
                raise ValueError(
                    f"{name} shape mismatch: expected {expected_shape}, "
                    f"got {features.shape}"
                )
        return True


class FeatureDriftDetector:
    """Detects anomalous feature values that suggest data drift."""
    
    DRIFT_WARNING_THRESHOLD = 0.1  # Warn if >10% of features are suspicious
    
    @staticmethod
    def detect_drift(
        features_dict: dict[str, float],
        warn_on_drift: bool = True,
    ) -> dict[str, list[str]]:
        """
        Detect suspicious feature values indicating data drift.
        
        Args:
            features_dict: Dictionary of feature_name -> value
            warn_on_drift: Whether to log warnings for drift
            
        Returns:
            Dictionary with suspicious features and warnings
        """
        suspicious = {
            "out_of_bounds": [],
            "warnings": [],
        }
        
        for feature_name, value in features_dict.items():
            drift_warning = FeatureDriftDetector._check_feature_drift(
                feature_name, value
            )
            if drift_warning:
                suspicious["out_of_bounds"].append(feature_name)
                suspicious["warnings"].append(drift_warning)
        
        # Calculate drift percentage
        drift_percentage = len(suspicious["out_of_bounds"]) / len(features_dict)
        
        if warn_on_drift and drift_percentage > 0:
            for warning in suspicious["warnings"]:
                logger.warning(f"Feature drift detected: {warning}")
        
        return suspicious
    
    @staticmethod
    def _check_feature_drift(feature_name: str, value: float) -> Optional[str]:
        """
        Check if a single feature value shows drift.
        
        Args:
            feature_name: Name of feature
            value: Feature value
            
        Returns:
            Warning message if drift detected, None otherwise
        """
        if feature_name not in FEATURE_BOUNDS:
            return None  # No bounds defined for this feature
        
        min_bound, max_bound = FEATURE_BOUNDS[feature_name]
        
        # Check if value is outside bounds
        if value < min_bound or value > max_bound:
            return (
                f"Feature '{feature_name}' value {value} outside bounds "
                f"[{min_bound}, {max_bound}] (possible data drift)"
            )
        
        return None
    
    @staticmethod
    def validate_and_detect_drift(
        features_dict: dict[str, float],
        strict_mode: bool = False,
    ) -> bool:
        """
        Validate features and detect drift. In strict mode, fail on drift.
        
        Args:
            features_dict: Dictionary of feature_name -> value
            strict_mode: If True, raise on drift; if False, warn only
            
        Returns:
            True if features valid (or passed loose validation)
            
        Raises:
            ValueError: If strict_mode=True and drift detected
        """
        drift_info = FeatureDriftDetector.detect_drift(
            features_dict, warn_on_drift=True
        )
        
        if drift_info["out_of_bounds"]:
            message = (
                f"Feature drift detected in: {drift_info['out_of_bounds']}"
            )
            if strict_mode:
                raise ValueError(message)
            else:
                logger.warning(message)
        
        return True


def _shapes_compatible(actual: tuple, expected: tuple) -> bool:
    """Check if actual shape is compatible with expected (with None wildcards)."""
    if len(actual) != len(expected):
        return False
    for a, e in zip(actual, expected):
        if e is not None and a != e:
            return False
    return True


class CoordinateValidator:
    """Validates geographic coordinates."""
    
    @staticmethod
    def validate_latitude(lat: float, name: str = "latitude") -> bool:
        """
        Validate latitude value.
        
        Args:
            lat: Latitude value
            name: Name for logging
            
        Returns:
            True if valid
            
        Raises:
            ValueError: If invalid
        """
        if not (-90.0 <= lat <= 90.0):
            raise ValueError(
                f"{name} {lat} outside valid range [-90, 90]"
            )
        return True
    
    @staticmethod
    def validate_longitude(lon: float, name: str = "longitude") -> bool:
        """
        Validate longitude value.
        
        Args:
            lon: Longitude value
            name: Name for logging
            
        Returns:
            True if valid
            
        Raises:
            ValueError: If invalid
        """
        if not (-180.0 <= lon <= 180.0):
            raise ValueError(
                f"{name} {lon} outside valid range [-180, 180]"
            )
        return True
    
    @staticmethod
    def validate_coordinates(
        latitude: float,
        longitude: float,
        name_prefix: str = "",
    ) -> bool:
        """
        Validate latitude and longitude pair.
        
        Args:
            latitude: Latitude value
            longitude: Longitude value
            name_prefix: Prefix for logging (e.g., "pickup")
            
        Returns:
            True if valid
            
        Raises:
            ValueError: If invalid
        """
        CoordinateValidator.validate_latitude(
            latitude, f"{name_prefix}_latitude"
        )
        CoordinateValidator.validate_longitude(
            longitude, f"{name_prefix}_longitude"
        )
        return True
