"""Fixed feature configuration matching training environment exactly."""

from __future__ import annotations

from typing import Final

# CRITICAL: Feature order MUST match the exact order used during model training
# Any deviation will result in incorrect predictions
# This is NOT negotiable and must be validated at startup
FEATURE_COLUMNS: Final[list[str]] = [
    "distance_km",              # 0: Distance in kilometers (0-50, normalized)
    "driver_rating",            # 1: Driver rating (1.0-5.0, normalized to 0-1)
    "acceptance_rate",          # 2: Acceptance rate (0-100%, normalized to 0-1)
    "cancellation_rate",        # 3: Cancellation rate (0-100%, normalized and inverted to 0-1)
    "behavior_score",           # 4: Behavior score (0-100, normalized to 0-1)
    "available_seats",          # 5: Available seats (1-8, normalized to 0-1)
    "traffic_level",            # 6: Traffic level (0-1, normalized and inverted to 0-1)
    "direction_similarity",     # 7: Direction similarity (0-1, already normalized)
    "seat_compatibility",       # 8: Seat compatibility score (0-1)
    "vehicle_compatibility",    # 9: Vehicle compatibility score (0-1)
]

# Expected model input shape: (batch_size, 10)
EXPECTED_FEATURE_COUNT: Final[int] = len(FEATURE_COLUMNS)

# Feature bounds for drift detection
FEATURE_BOUNDS: Final[dict[str, tuple[float, float]]] = {
    "distance_km": (0.0, 50.0),                    # Max 50km
    "driver_rating": (1.0, 5.0),                   # 1-5 scale
    "acceptance_rate": (0.0, 100.0),               # Percentage
    "cancellation_rate": (0.0, 100.0),             # Percentage
    "behavior_score": (0.0, 100.0),                # Percentage
    "available_seats": (1.0, 8.0),                 # 1-8 passengers
    "traffic_level": (0.0, 1.0),                   # 0-1 scale
    "direction_similarity": (0.0, 1.0),            # 0-1 scale
    "seat_compatibility": (0.0, 1.0),              # 0-1 compatibility
    "vehicle_compatibility": (0.0, 1.0),           # 0-1 compatibility
}

# Normalized bounds after scaler transform (if scaler used)
# If using StandardScaler: typically within [-3, 3] after normalization
NORMALIZED_BOUNDS: Final[dict[str, tuple[float, float]]] = {
    feature: (-4.0, 4.0) for feature in FEATURE_COLUMNS  # Allow some margin beyond 3-sigma
}

# Expected output shape: (batch_size, 1) or (batch_size,)
EXPECTED_OUTPUT_SHAPES: Final[list] = [
    (None, 1),   # Model outputs (batch, 1)
    (None,),     # Or (batch,) - both acceptable
]


def validate_feature_order(features: list[str]) -> bool:
    """
    Validate that feature list matches expected order.
    
    Args:
        features: Feature names to validate
        
    Returns:
        True if valid, raises ValueError otherwise
        
    Raises:
        ValueError: If feature order or count doesn't match
    """
    if len(features) != EXPECTED_FEATURE_COUNT:
        raise ValueError(
            f"Feature count mismatch: expected {EXPECTED_FEATURE_COUNT}, "
            f"got {len(features)}"
        )
    
    for i, (expected, actual) in enumerate(zip(FEATURE_COLUMNS, features)):
        if expected != actual:
            raise ValueError(
                f"Feature order mismatch at position {i}: "
                f"expected '{expected}', got '{actual}'"
            )
    
    return True


def validate_feature_bounds(feature_name: str, value: float, warn_only: bool = False) -> bool:
    """
    Validate that feature value is within expected bounds.
    
    Args:
        feature_name: Name of feature
        value: Feature value
        warn_only: If True, log warning instead of raising error
        
    Returns:
        True if within bounds
        
    Raises:
        ValueError: If out of bounds and warn_only=False
    """
    if feature_name not in FEATURE_BOUNDS:
        return True  # Unknown feature, skip validation
    
    min_val, max_val = FEATURE_BOUNDS[feature_name]
    
    if not (min_val <= value <= max_val):
        msg = (
            f"Feature '{feature_name}' value {value} outside expected bounds "
            f"[{min_val}, {max_val}]"
        )
        if warn_only:
            return False
        raise ValueError(msg)
    
    return True


def get_feature_index(feature_name: str) -> int:
    """
    Get the index of a feature in the fixed order.
    
    Args:
        feature_name: Name of feature
        
    Returns:
        Index in FEATURE_COLUMNS
        
    Raises:
        ValueError: If feature not found
    """
    try:
        return FEATURE_COLUMNS.index(feature_name)
    except ValueError:
        raise ValueError(f"Feature '{feature_name}' not in FEATURE_COLUMNS")
