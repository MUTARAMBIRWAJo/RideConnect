import os
import json
from typing import Dict
from app.core.config import settings

MODEL_PATH = os.path.join(settings.MODEL_DIR, "demand_model.keras")


class DemandModel:
    def __init__(self):
        # lazy load
        self.model = None

    def _load(self):
        try:
            import tensorflow as tf
            self.model = tf.keras.models.load_model(MODEL_PATH)
        except Exception:
            self.model = None

    async def predict(self, zone: str, timestamp: str) -> Dict:
        # Use Redis cache first
        try:
            from app.core.redis_client import get_redis
            r = get_redis()
            key = f"demand:{zone}:{timestamp}"
            cached = await r.get(key)
            if cached:
                import json
                return json.loads(cached)
        except Exception:
            r = None

        if self.model is None:
            self._load()

        base = (hash(zone) % 100) / 100.0
        surge = 0.2 if "08:" in timestamp or "17:" in timestamp else 0.05
        result = {"predicted_demand": round(min(1.0, base + surge), 4), "surge_probability": round(surge, 4)}

        if r is not None:
            try:
                import json
                await r.set(key, json.dumps(result), ex=60)
            except Exception:
                pass

        return result
