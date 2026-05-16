"""Production /ml endpoints."""

from __future__ import annotations

import asyncio
import time
from datetime import datetime, timezone
from typing import Any

import pandas as pd
from fastapi import APIRouter, HTTPException, Request
from pydantic import BaseModel, Field, model_validator

from app.core.config import settings
from app.core.logging import get_logger
from app.middleware import get_request_id
from app.services.behavior_detector_loader import get_behavior_detector_loader
from app.services.ranker_loader import DriverRankerError, get_driver_ranker_loader

logger = get_logger(__name__)
router = APIRouter(prefix="/ml", tags=["ml"])


class DriverRankerBookingContext(BaseModel):
    """Booking context shared across candidate rows."""

    pickup_lat: float = Field(..., ge=-90, le=90)
    pickup_lng: float = Field(..., ge=-180, le=180)
    hour_of_day: int = Field(..., ge=0, le=23)
    day_of_week: int = Field(..., ge=0, le=6)


class DriverRankerCandidate(BaseModel):
    """Candidate driver features for ranker inference."""

    driver_id: str | int
    distance_to_pickup_km: float = Field(..., ge=0)
    driver_rating: float = Field(..., ge=0, le=5)
    acceptance_rate: float = Field(..., ge=0, le=1)
    vehicle_type: str = Field(..., min_length=1)


class DriverRankerRequest(BaseModel):
    """Driver ranking request."""

    booking: DriverRankerBookingContext | None = None
    booking_context: DriverRankerBookingContext | None = None
    candidates: list[DriverRankerCandidate] = Field(..., min_length=1, max_length=20)

    @model_validator(mode="after")
    def normalize_booking_context(self) -> "DriverRankerRequest":
        if self.booking_context is None:
            self.booking_context = self.booking
        if self.booking_context is None:
            raise ValueError("booking or booking_context is required")
        return self


class GPSReading(BaseModel):
    """GPS reading for anomaly detection."""

    speed_kmh: float = Field(..., ge=0, le=300)
    acceleration_ms2: float = Field(..., ge=-15, le=15)
    heading_change_degrees: float = Field(..., ge=0, le=360)
    route_deviation_meters: float = Field(..., ge=0, le=5000)
    stop_duration_seconds: float = Field(..., ge=0, le=7200)


class AnomalyDetectionRequest(BaseModel):
    """Request schema for the production /ml anomaly endpoint."""

    gps_reading: GPSReading | None = None
    speed_kmh: float | None = Field(None, ge=0, le=300)
    acceleration_ms2: float | None = Field(None, ge=-15, le=15)
    heading_change_degrees: float | None = Field(None, ge=0, le=360)
    route_deviation_meters: float | None = Field(None, ge=0, le=5000)
    stop_duration_seconds: float | None = Field(None, ge=0, le=7200)

    @model_validator(mode="after")
    def normalize_gps_reading(self) -> "AnomalyDetectionRequest":
        if self.gps_reading is not None:
            return self

        values = {
            "speed_kmh": self.speed_kmh,
            "acceleration_ms2": self.acceleration_ms2,
            "heading_change_degrees": self.heading_change_degrees,
            "route_deviation_meters": self.route_deviation_meters,
            "stop_duration_seconds": self.stop_duration_seconds,
        }
        if any(value is None for value in values.values()):
            raise ValueError("gps_reading or all flat GPS fields are required")

        self.gps_reading = GPSReading(**values)
        return self


class DemandPredictionRequest(BaseModel):
    """Compatibility demand request for /ml/predict-demand."""

    latitude: float
    longitude: float
    hour: int = Field(..., ge=0, le=23)
    day_of_week: int = Field(..., ge=0, le=6)


def _request_id(request: Request) -> str | None:
    try:
        return get_request_id()
    except Exception:
        return getattr(request.state, "request_id", None)


def _model_dump(model: BaseModel) -> dict[str, Any]:
    if hasattr(model, "model_dump"):
        return model.model_dump()
    return model.dict()


@router.get("/rank-drivers")
async def rank_drivers_help() -> dict[str, Any]:
    """Browser-friendly help for the driver ranking endpoint."""
    try:
        ranker = get_driver_ranker_loader()
        valid_types = ranker.valid_vehicle_types()
    except Exception:
        valid_types = []

    return {
        "detail": "Use POST for this endpoint.",
        "expected_method": "POST",
        "valid_vehicle_types": valid_types,
    }


@router.post("/rank-drivers")
async def rank_drivers(payload: DriverRankerRequest, request: Request) -> dict[str, Any]:
    """Rank candidate drivers by predicted successful assignment probability."""
    started_at = time.perf_counter()
    request_id = _request_id(request)

    try:
        ranker = get_driver_ranker_loader()
    except RuntimeError:
        logger.error("Driver ranker not initialized", extra={"request_id": request_id})
        raise HTTPException(status_code=503, detail="driver ranker model is not loaded")

    if not ranker.is_loaded():
        raise HTTPException(status_code=503, detail="driver ranker model is not loaded")

    valid_vehicle_types = ranker.valid_vehicle_types()
    for candidate in payload.candidates:
        if candidate.vehicle_type not in valid_vehicle_types:
            raise HTTPException(
                status_code=422,
                detail={
                    "message": "Invalid vehicle_type",
                    "offending_value": candidate.vehicle_type,
                    "valid_values": valid_vehicle_types,
                },
            )

    try:
        rows: list[dict[str, Any]] = []
        for candidate in payload.candidates:
            rows.append(
                {
                    "distance_to_pickup_km": candidate.distance_to_pickup_km,
                    "driver_rating": candidate.driver_rating,
                    "acceptance_rate": candidate.acceptance_rate,
                    "vehicle_type_encoded": ranker.encode_vehicle_type(candidate.vehicle_type),
                    "hour_of_day": payload.booking_context.hour_of_day,
                    "day_of_week": payload.booking_context.day_of_week,
                }
            )

        features = pd.DataFrame(rows, columns=ranker.feature_columns)
        probabilities = await asyncio.wait_for(
            asyncio.to_thread(ranker.predict_proba, features),
            timeout=settings.INFERENCE_TIMEOUT,
        )

        ranked_candidates = []
        for candidate, score in zip(payload.candidates, probabilities, strict=True):
            ranked_candidates.append(
                {
                    "driver_id": candidate.driver_id,
                    "score": round(float(score), 4),
                    "assignment_confidence": round(float(score), 4),
                    "distance_to_pickup_km": candidate.distance_to_pickup_km,
                    "vehicle_type": candidate.vehicle_type,
                }
            )

        ranked_candidates.sort(key=lambda item: item["score"], reverse=True)
        for index, candidate in enumerate(ranked_candidates, start=1):
            candidate["rank"] = index

        metadata = ranker.get_model_metadata()
        best_driver = ranked_candidates[0]
        elapsed_ms = (time.perf_counter() - started_at) * 1000

        logger.info(
            "Driver ranking complete",
            extra={
                "request_id": request_id,
                "candidate_count": len(payload.candidates),
                "best_driver_id": best_driver["driver_id"],
                "top_score": best_driver["score"],
                "elapsed_inference_ms": elapsed_ms,
                "model_version": metadata["version"],
            },
        )

        return {
            "status": "success",
            "data": {
                "best_driver": best_driver,
                "ranked_candidates": ranked_candidates,
                "model_version": metadata["version"],
                "candidates_evaluated": len(payload.candidates),
            },
        }
    except HTTPException:
        raise
    except (ValueError, DriverRankerError) as exc:
        logger.error(
            f"Driver ranking inference failed: {str(exc)}",
            extra={"request_id": request_id},
            exc_info=True,
        )
        raise HTTPException(status_code=500, detail="driver ranking inference failed") from exc


@router.post("/predict-demand")
async def predict_demand(payload: DemandPredictionRequest) -> dict[str, float | int]:
    """Compatibility demand forecast alias for Laravel and mobile clients."""
    commute_peak = payload.hour in {7, 8, 9, 17, 18, 19}
    weekend = payload.day_of_week >= 5
    kigali_core = -2.05 <= payload.latitude <= -1.85 and 29.95 <= payload.longitude <= 30.20

    demand_level = 0.35
    if kigali_core:
        demand_level += 0.20
    if commute_peak:
        demand_level += 0.30
    if weekend:
        demand_level -= 0.10

    demand_level = max(0.05, min(1.0, demand_level))

    return {
        "demand_level": float(demand_level),
        "expected_wait_time_minutes": int(max(2, round(15 - demand_level * 10))),
        "confidence": 0.72 if kigali_core else 0.60,
    }


@router.post("/detect-anomaly")
async def detect_anomaly(payload: AnomalyDetectionRequest, request: Request) -> dict[str, Any]:
    """Production alias for Isolation Forest driver behavior anomaly detection."""
    request_id = _request_id(request)

    try:
        loader = get_behavior_detector_loader()
    except RuntimeError:
        logger.error("Behavior detector not initialized", extra={"request_id": request_id})
        raise HTTPException(status_code=503, detail="behavior detector is not loaded")

    if not loader.is_loaded():
        raise HTTPException(status_code=503, detail="behavior detector is not loaded")

    try:
        is_anomaly, anomaly_score = loader.predict(_model_dump(payload.gps_reading))
        severity = "low"
        if is_anomaly and anomaly_score < -0.20:
            severity = "high"
        elif is_anomaly and anomaly_score < -0.05:
            severity = "medium"

        return {
            "status": "success",
            "data": {
                "is_anomaly": is_anomaly,
                "anomaly_score": round(float(anomaly_score), 4),
                "severity": severity,
                "model_version": "behavior_isolation_forest_v1",
                "detected_at": datetime.now(timezone.utc).isoformat().replace("+00:00", "Z"),
            },
        }
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc
    except Exception as exc:
        logger.error(
            f"Anomaly detection failed: {str(exc)}",
            extra={"request_id": request_id},
            exc_info=True,
        )
        raise HTTPException(status_code=500, detail="anomaly detection failed") from exc
    except asyncio.TimeoutError as exc:
        logger.error(
            "Driver ranking inference timed out",
            extra={"request_id": request_id},
            exc_info=True,
        )
        raise HTTPException(status_code=500, detail="driver ranking inference failed") from exc
    except Exception as exc:
        logger.error(
            f"Unexpected driver ranking failure: {str(exc)}",
            extra={"request_id": request_id},
            exc_info=True,
        )
        raise HTTPException(status_code=500, detail="driver ranking inference failed") from exc
