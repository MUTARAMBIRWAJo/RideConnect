from typing import Dict
from typing import Dict
from app.core.redis_client import get_redis
import json

# Default matching weights (can be tuned via admin endpoint)
_weights = {
    "distance": 0.35,
    "rating": 0.2,
    "acceptance": 0.15,
    "cancellation": 0.1,
    "behavior": 0.1,
    "direction": 0.1,
}


async def _get_redis_weights() -> Dict[str, float]:
    try:
        r = get_redis()
        raw = await r.get("matching:weights")
        if raw:
            return json.loads(raw)
    except Exception:
        pass
    return None


def get_weights() -> Dict[str, float]:
    # attempt to read sync from redis (fallback to in-memory)
    try:
        import asyncio
        loop = asyncio.get_event_loop()
        res = loop.run_until_complete(_get_redis_weights())
        if res:
            return res
    except Exception:
        pass
    # attempt DB
    try:
        from app.services.weights_db import get_weights_db
        dbw = get_weights_db()
        if dbw:
            return dbw
    except Exception:
        pass
    return dict(_weights)


def update_weights(new_weights: Dict[str, float]):
    for k, v in new_weights.items():
        if k in _weights:
            _weights[k] = float(v)
    # persist to redis if available
    try:
        import asyncio
        r = get_redis()
        asyncio.get_event_loop().run_until_complete(r.set("matching:weights", json.dumps(_weights)))
    except Exception:
        pass
