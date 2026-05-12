"""DEPRECATED: Heuristic demand model replaced by V2 LSTM on root ML service."""
import logging
from typing import Dict

logger = logging.getLogger(__name__)


class DemandModel:
    """DEPRECATED: Use POST /ml/predict-demand on root service instead."""

    def __init__(self):
        logger.warning(
            "DemandModel (heuristic) is deprecated. "
            "Use V2 LSTM demand endpoint at POST /ml/predict-demand on root service."
        )

    async def predict(self, zone: str, timestamp: str) -> Dict:
        """DEPRECATED: Returns placeholder. Migrate to /ml/predict-demand."""
        logger.warning(
            f"Deprecated DemandModel.predict() called for zone={zone}, timestamp={timestamp}. "
            "Please migrate to POST /ml/predict-demand."
        )
        return {
            "predicted_demand": 0.0,
            "surge_probability": 0.0,
            "_note": "Deprecated heuristic. Use /ml/predict-demand instead.",
        }
