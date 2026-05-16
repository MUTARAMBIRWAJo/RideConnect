"""Driver ranking model loading and inference management."""

from __future__ import annotations

import json
import os
import time
from pathlib import Path
from typing import Any, Optional

import joblib
import numpy as np
import pandas as pd

from app.core.config import settings
from app.core.logging import get_logger

logger = get_logger(__name__)


class DriverRankerError(Exception):
    """Raised when driver ranker loading or inference fails."""


class DriverRankerLoader:
    """Loads and manages the XGBoost driver ranking model artifacts."""

    def __init__(self) -> None:
        model_dir = Path(settings.MODEL_DIR)
        self.model_path = os.getenv("RANKER_MODEL_PATH", str(model_dir / "driver_ranker.pkl"))
        self.encoder_path = os.getenv("RANKER_ENCODER_PATH", str(model_dir / "vehicle_encoder.pkl"))
        self.config_path = os.getenv("RANKER_CONFIG_PATH", str(model_dir / "ranker_feature_config.json"))

        self.model: Any = None
        self.vehicle_encoder: Any = None
        self.feature_columns: list[str] = []
        self.config: dict[str, Any] = {}
        self.initialized_at: float | None = None

    async def initialize(self) -> None:
        """Async startup hook used by the service lifespan."""
        self.load()

    def load(self) -> None:
        """Load and validate the model, encoder, and feature config artifacts."""
        try:
            for path, name in [
                (self.model_path, "driver ranker model"),
                (self.encoder_path, "vehicle encoder"),
                (self.config_path, "ranker feature config"),
            ]:
                if not os.path.exists(path):
                    raise FileNotFoundError(f"{name} artifact not found at {path}")

            logger.info(f"Loading driver ranker model from {self.model_path}")
            self.model = joblib.load(self.model_path)

            logger.info(f"Loading ranker vehicle encoder from {self.encoder_path}")
            self.vehicle_encoder = joblib.load(self.encoder_path)

            logger.info(f"Loading ranker feature config from {self.config_path}")
            with open(self.config_path, "r", encoding="utf-8") as handle:
                self.config = json.load(handle)

            self.feature_columns = list(self.config.get("feature_columns") or [])
            self._validate_artifacts()
            self.initialized_at = time.time()

            logger.info(
                "Driver ranker loaded successfully. "
                f"Features: {self.feature_columns}, "
                f"Vehicle types: {self.valid_vehicle_types()}, "
                f"Model type: {self.model.__class__.__name__}"
            )
        except Exception as exc:
            logger.error(f"Failed to load driver ranker: {str(exc)}", exc_info=True)
            self.model = None
            self.vehicle_encoder = None
            self.feature_columns = []
            self.config = {}
            self.initialized_at = None
            raise

    def _validate_artifacts(self) -> None:
        """Validate runtime artifact integrity before accepting the loader."""
        if self.model is None:
            raise DriverRankerError("Driver ranker model artifact loaded as None")

        if self.vehicle_encoder is None:
            raise DriverRankerError("Vehicle encoder artifact loaded as None")

        if not self.feature_columns:
            raise DriverRankerError("ranker_feature_config.json is missing feature_columns")

        if not hasattr(self.model, "predict_proba"):
            raise DriverRankerError("Driver ranker model does not expose predict_proba")

        if not hasattr(self.vehicle_encoder, "classes_"):
            raise DriverRankerError("Vehicle encoder does not expose classes_")

        model_feature_count = getattr(self.model, "n_features_in_", None)
        if model_feature_count is not None and int(model_feature_count) != len(self.feature_columns):
            raise DriverRankerError(
                f"Ranker feature count mismatch: model expects {model_feature_count}, "
                f"config provides {len(self.feature_columns)}"
            )

        configured_vehicle_types = self.config.get("vehicle_classes")
        if configured_vehicle_types is not None:
            encoder_types = self.valid_vehicle_types()
            if list(configured_vehicle_types) != encoder_types:
                raise DriverRankerError(
                    "Vehicle config classes do not match encoder classes: "
                    f"config={configured_vehicle_types}, encoder={encoder_types}"
                )

    def is_loaded(self) -> bool:
        """Check whether the ranker is ready for inference."""
        return (
            self.model is not None
            and self.vehicle_encoder is not None
            and len(self.feature_columns) > 0
        )

    def valid_vehicle_types(self) -> list[str]:
        """Return vehicle types accepted by the trained encoder."""
        if self.vehicle_encoder is None or not hasattr(self.vehicle_encoder, "classes_"):
            return []
        return [str(value) for value in self.vehicle_encoder.classes_]

    def encode_vehicle_type(self, vehicle_type: str) -> int:
        """Encode a vehicle type using the trained encoder."""
        if not self.is_loaded():
            raise RuntimeError("Driver ranker is not loaded")

        normalized = str(vehicle_type)
        valid_types = self.valid_vehicle_types()
        if normalized not in valid_types:
            raise ValueError(f"Invalid vehicle_type '{vehicle_type}'. Valid values: {valid_types}")

        try:
            return int(self.vehicle_encoder.transform([normalized])[0])
        except Exception as exc:
            logger.error(
                f"Vehicle type encoding failed for vehicle_type={vehicle_type}",
                exc_info=True,
            )
            raise DriverRankerError("Vehicle type encoding failed") from exc

    def predict_proba(self, features: pd.DataFrame) -> list[float]:
        """Predict positive-class successful assignment probabilities."""
        if not self.is_loaded():
            raise RuntimeError("Driver ranker is not loaded")

        if list(features.columns) != self.feature_columns:
            raise ValueError(
                "Ranker feature order mismatch: "
                f"expected={self.feature_columns}, got={list(features.columns)}"
            )

        try:
            probabilities = self.model.predict_proba(features)
            probabilities = np.asarray(probabilities, dtype=np.float32)

            if probabilities.ndim != 2:
                raise DriverRankerError(f"Unexpected probability shape: {probabilities.shape}")

            classes = getattr(self.model, "classes_", None)
            positive_index = 1
            if classes is not None:
                class_values = list(classes)
                if 1 not in class_values:
                    raise DriverRankerError(f"Positive class 1 missing from model classes: {class_values}")
                positive_index = class_values.index(1)

            return [float(probability) for probability in probabilities[:, positive_index]]
        except ValueError:
            raise
        except Exception as exc:
            logger.error("Driver ranker inference failed", exc_info=True)
            raise DriverRankerError("Driver ranker inference failed") from exc

    def get_model_metadata(self) -> dict[str, Any]:
        """Return runtime metadata for health and observability."""
        uptime_seconds = 0.0
        if self.initialized_at is not None:
            uptime_seconds = time.time() - self.initialized_at

        metrics = self.config.get("training_metrics", {}) if self.config else {}

        return {
            "loaded": self.is_loaded(),
            "model_name": self.config.get("model_name", "driver_ranker") if self.config else "driver_ranker",
            "algorithm": self.config.get("algorithm", self.model.__class__.__name__ if self.model else None),
            "version": settings.MODEL_VERSION,
            "model_path": self.model_path,
            "encoder_path": self.encoder_path,
            "config_path": self.config_path,
            "feature_columns": self.feature_columns,
            "valid_vehicle_types": self.valid_vehicle_types(),
            "training_metrics": metrics,
            "uptime_seconds": uptime_seconds,
        }

    def cleanup(self) -> None:
        """Release loaded artifacts."""
        self.model = None
        self.vehicle_encoder = None
        self.feature_columns = []
        self.config = {}
        self.initialized_at = None
        logger.info("Driver ranker cleaned up")


_driver_ranker_loader: Optional[DriverRankerLoader] = None


def get_driver_ranker_loader() -> DriverRankerLoader:
    """Get the global driver ranker loader instance."""
    global _driver_ranker_loader
    if _driver_ranker_loader is None:
        raise RuntimeError("Driver ranker loader not initialized")
    return _driver_ranker_loader


def initialize_driver_ranker_loader() -> DriverRankerLoader:
    """Initialize the global driver ranker loader."""
    global _driver_ranker_loader
    _driver_ranker_loader = DriverRankerLoader()
    return _driver_ranker_loader
