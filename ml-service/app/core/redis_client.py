import os
from app.core.config import settings

_redis = None


def get_redis():
    """Return an async redis client or raise ImportError if not available."""
    global _redis
    if _redis is not None:
        return _redis
    try:
        import redis.asyncio as aioredis
        _redis = aioredis.from_url(settings.REDIS_URL)
        return _redis
    except Exception:
        raise


def get_rq_queue():
    """Return an RQ Queue using redis-py. Raises if redis not installed."""
    try:
        from rq import Queue
        import redis
        redis_conn = redis.from_url(settings.REDIS_URL)
        return Queue("default", connection=redis_conn)
    except Exception:
        raise
