from fastapi import APIRouter, HTTPException
from typing import List
from app.schemas.schemas import (
    RideRequest,
    DriverCandidate,
    MatchResponse,
    DemandRequest,
    DemandResponse,
    ETARequest,
    ETAResponse,
)
from app.services.matcher import match_drivers
from app.services.demand_model import DemandModel
from app.services.eta_model import ETAModel
from app.core.redis_client import get_redis
import json

router = APIRouter()

_demand_model = DemandModel()
_eta_model = ETAModel()


@router.post("/predict/match-driver", response_model=MatchResponse)
async def predict_match(ride_request: RideRequest, candidate_drivers: List[DriverCandidate]):
    try:
        r = None
        try:
            r = get_redis()
        except Exception:
            r = None

        key = None
        ranked = None
        payload = {"ride_request": ride_request.dict(), "candidates": [d.dict() for d in candidate_drivers]}
        try:
            key = "match:" + str(abs(hash(json.dumps(payload, sort_keys=True))))
            if r is not None:
                cached = await r.get(key)
                if cached:
                    ranked = json.loads(cached)
        except Exception:
            ranked = None

        if ranked is None:
            ranked = match_drivers(ride_request.dict(), [d.dict() for d in candidate_drivers])
            if r is not None and key is not None:
                try:
                    await r.set(key, json.dumps(ranked), ex=15)
                except Exception:
                    pass

        return {"best_driver_id": ranked[0]["driver_id"], "ranked_drivers": ranked}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/predict/demand", response_model=DemandResponse)
async def predict_demand(req: DemandRequest):
    pred = await _demand_model.predict(req.zone, req.timestamp)
    return DemandResponse(predicted_demand=pred["predicted_demand"], surge_probability=pred.get("surge_probability", 0.0))


@router.post("/predict/eta", response_model=ETAResponse)
async def predict_eta(req: ETARequest):
    try:
        r = None
        try:
            r = get_redis()
        except Exception:
            r = None
        key = None
        eta = None
        try:
            key = "eta:" + str(abs(hash(f"{req.pickup_latitude},{req.pickup_longitude},{req.destination_latitude},{req.destination_longitude},{req.traffic_level}")))
            if r is not None:
                cached = await r.get(key)
                if cached:
                    eta = int(cached)
        except Exception:
            eta = None

        if eta is None:
            eta = await _eta_model.predict(req)
            if r is not None and key is not None:
                try:
                    await r.set(key, str(eta), ex=30)
                except Exception:
                    pass

        return ETAResponse(eta_seconds=eta)
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/retrain")
async def retrain_models():
    # Enqueue retraining job using RQ
    try:
        from app.core.redis_client import get_rq_queue
        q = get_rq_queue()
        q.enqueue("train.train_demand_model.main")
        q.enqueue("train.train_matching_model.main")
        return {"status": "retraining_enqueued"}
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"failed to enqueue retrain: {e}")
