"""Behavior anomaly detection model loading and management."""
import json
import os
from typing import Optional, Tuple

try:
    import joblib
except ModuleNotFoundError:
    joblib = None

import numpy as np

from app.core.config import settings
from app.core.logging import get_logger

logger = get_logger(__name__)


class BehaviorDetectorError(Exception):
    """Raised when behavior detector loading or prediction fails."""
    pass


class BehaviorDetectorLoader:
    """Handles loading and managing the Isolation Forest behavior detector."""

    def __init__(self):
        """Initialize behavior detector loader."""
        self.detector = None
        self.scaler = None
        self.feature_columns = None
        self.detector_path = settings.BEHAVIOR_DETECTOR_PATH
        self.scaler_path = settings.BEHAVIOR_SCALER_PATH
        self.config_path = settings.BEHAVIOR_CONFIG_PATH
        self.initialized_at = None

    async def initialize(self) -> None:
        """
        Load the Isolation Forest detector, scaler, and feature config at startup.

        Raises:
            FileNotFoundError: If model files don't exist
            BehaviorDetectorError: If loading fails
            Exception: If unexpected error occurs
        """
        self.load()

    def load(self) -> None:
        """Load the behavior detector artifacts synchronously."""
        try:
            # Check if files exist
            for path, name in [
                (self.detector_path, "detector"),
                (self.scaler_path, "scaler"),
                (self.config_path, "config"),
            ]:
                if not os.path.exists(path):
                    raise FileNotFoundError(f"Behavior {name} file not found at {path}")

            if joblib is None:
                raise RuntimeError("joblib is required to load behavior detector artifacts")

            logger.info(f"Loading behavior detector from {self.detector_path}")
            self.detector = joblib.load(self.detector_path)

            logger.info(f"Loading behavior scaler from {self.scaler_path}")
            self.scaler = joblib.load(self.scaler_path)

            logger.info(f"Loading behavior feature config from {self.config_path}")
            with open(self.config_path, "r", encoding="utf-8") as f:
                config = json.load(f)
                self.feature_columns = config.get("feature_columns", [])

            self.initialized_at = __import__("time").time()

            logger.info(
                f"Behavior detector loaded successfully. "
                f"Features: {self.feature_columns}, "
                f"Feature count: {len(self.feature_columns)}"
            )
            logger.info(
                f"Detector type: {self.detector.__class__.__name__}, "
                f"n_estimators: {getattr(self.detector, 'n_estimators', 'N/A')}"
            )

        except Exception as e:
            logger.error(f"Failed to load behavior detector: {str(e)}", exc_info=True)
            raise

    def predict(self, features_dict: dict) -> Tuple[bool, float]:
        """
        Predict whether a GPS reading is anomalous.

        Args:
            features_dict: Dictionary with keys matching FEATURE_COLUMNS.
                          Must include: speed_kmh, acceleration_ms2, heading_change_degrees,
                          route_deviation_meters, stop_duration_seconds

        Returns:
            Tuple of (is_anomaly, anomaly_score)
            - is_anomaly: True if IsolationForest.predict() returned -1, False if +1
            - anomaly_score: Raw score from IsolationForest.score_samples() (lower = more anomalous)

        Raises:
            RuntimeError: If model not loaded
            ValueError: If features missing or incorrect
            BehaviorDetectorError: If prediction fails
        """
        if self.detector is None or self.scaler is None or not self.feature_columns:
            raise RuntimeError("Behavior detector not loaded")

        try:
            # Extract features in the EXACT order from feature_columns
            feature_values = []
            for col in self.feature_columns:
                if col not in features_dict:
                    raise ValueError(f"Missing required feature: {col}")
                feature_values.append(features_dict[col])

            # Build feature array: shape (1, 5)
            features_array = np.array(feature_values, dtype=np.float32).reshape(1, -1)

            # Scale features
            scaled_features = self.scaler.transform(features_array)

            # Get predictions
            predictions = self.detector.predict(scaled_features)
            is_anomaly = bool(predictions[0] == -1)

            # Get anomaly scores (lower = more anomalous)
            scores = self.detector.score_samples(scaled_features)
            anomaly_score = float(scores[0])

            return (is_anomaly, anomaly_score)

        except ValueError:
            raise
        except Exception as e:
            logger.error(f"Behavior detector prediction failed: {str(e)}", exc_info=True)
            raise BehaviorDetectorError(f"Prediction failed: {str(e)}") from e

    def is_loaded(self) -> bool:
        """Check if behavior detector is loaded and ready."""
        return (
            self.detector is not None
            and self.scaler is not None
            and self.feature_columns is not None
            and len(self.feature_columns) > 0
        )

    def cleanup(self) -> None:
        """Clean up detector resources."""
        self.detector = None
        self.scaler = None
        self.feature_columns = None
        logger.info("Behavior detector cleaned up")


# Global singleton instance
_behavior_detector_loader: Optional[BehaviorDetectorLoader] = None


def get_behavior_detector_loader() -> BehaviorDetectorLoader:
    """Get the global behavior detector loader instance."""
    global _behavior_detector_loader
    if _behavior_detector_loader is None:
        raise RuntimeError("Behavior detector loader not initialized")
    return _behavior_detector_loader


def initialize_behavior_detector_loader() -> BehaviorDetectorLoader:
    """Initialize the global behavior detector loader."""
    global _behavior_detector_loader
    _behavior_detector_loader = BehaviorDetectorLoader()
    return _behavior_detector_loader


# Convenience export for use in startup
behavior_detector_loader = BehaviorDetectorLoader()
