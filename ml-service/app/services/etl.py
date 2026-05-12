from app.services.supabase_client import fetch_table_rows
from app.services.feature_engineer import build_feature_vector
from app.core.redis_client import get_redis, get_rq_queue
import json


def enqueue_training_samples(limit: int = 100):
    """Fetch recent rides and drivers from Supabase and push feature vectors to Redis list.

    Falls back to enqueuing an RQ job if Redis list is unavailable.
    """
    rides = fetch_table_rows("rides", limit)
    drivers = fetch_table_rows("drivers", limit)
    if not rides or not drivers:
        return 0

    try:
        r = get_redis()
    except Exception:
        r = None

    count = 0
    for ride in rides:
        for driver in drivers:
            vec = build_feature_vector(ride, driver)
            payload = {"features": vec, "ride_id": ride.get("id"), "driver_id": driver.get("id")}
            sval = json.dumps(payload)
            try:
                if r is not None:
                    # push into a training queue list
                    import asyncio
                    asyncio.get_event_loop().run_until_complete(r.rpush("training:queue", sval))
                else:
                    q = get_rq_queue()
                    q.enqueue("train.train_matching_model.main", payload)
                count += 1
            except Exception:
                continue

    return count
