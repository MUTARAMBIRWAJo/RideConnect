from fastapi import APIRouter, Header, HTTPException, Query
from typing import Dict, Optional

from app.core.weights import get_weights, update_weights
from app.core.config import settings
from app.services.etl import enqueue_training_samples
from app.schemas.admin import WeightAuditLogResponse

router = APIRouter()


def _check_token(token: Optional[str]):
    if not token or token != settings.SECRET_KEY:
        raise HTTPException(status_code=403, detail="invalid admin token")


@router.get("/weights")
async def read_weights(x_admin_token: Optional[str] = Header(None)):
    _check_token(x_admin_token)
    return get_weights()


@router.post("/weights")
async def set_weights(payload: Dict[str, float], x_admin_token: Optional[str] = Header(None)):
    _check_token(x_admin_token)
    try:
        update_weights(payload)
        # Attempt to persist to DB if available
        try:
            from app.services.weights_db import set_weights_db, record_weight_audit
            set_weights_db(get_weights())
            record_weight_audit("admin", payload)
        except Exception:
            pass
        return {"status": "updated", "weights": get_weights()}
    except Exception as e:
        raise HTTPException(status_code=400, detail=str(e))


@router.get("/weights/audit", response_model=WeightAuditLogResponse)
async def read_weight_audit_logs(
    x_admin_token: Optional[str] = Header(None),
    limit: int = Query(50, ge=1, le=200),
    offset: int = Query(0, ge=0),
):
    _check_token(x_admin_token)
    try:
        from app.services.weights_db import get_weight_audit_logs

        logs = get_weight_audit_logs(limit=limit, offset=offset)
        return WeightAuditLogResponse(
            items=logs,
            limit=limit,
            offset=offset,
            total=len(logs),
        )
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/etl")
async def trigger_etl(x_admin_token: Optional[str] = Header(None)):
    _check_token(x_admin_token)
    try:
        # enqueue ETL job if RQ available, otherwise run synchronously
        try:
            from app.core.redis_client import get_rq_queue
            q = get_rq_queue()
            q.enqueue("train.etl_enqueue.main")
            return {"status": "enqueued"}
        except Exception:
            # fallback: run synchronously
            n = enqueue_training_samples()
            return {"status": "completed", "enqueued_samples": n}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
